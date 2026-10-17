<?php

namespace App\Account;

use App\Entity\Member;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\Workspace;
use App\Persistence\Transaction;
use App\Repository\MemberRepository;
use App\Security\PasswordPolicy;
use App\Token\Purpose;
use App\Token\TokenRefused;
use App\Token\Tokens;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Accounts: signing up, verifying the email address with the link that is sent (App\Token\Tokens,
 * Purpose::VerifyEmail, valid for 24 hours), choosing a new password with a link by email
 * (Purpose::ResetPassword, 30 minutes), and starting a Workspace of one's own. Emails go out
 * once what they are about has been stored (Transaction::afterCommit()); one that cannot be sent is
 * logged, and the User can ask for it again.
 */
final readonly class Accounts
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Tokens $tokens,
        private Transaction $transaction,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $hasher,
        private MemberRepository $members,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    /**
     * Signs a visitor up as an unverified User and emails them a link to verify their address.
     * The answer is the same whether the address has an account or not: when it has a verified
     * one, its owner is emailed that they have an account instead, and nothing changes. An
     * unverified one starts over with this name and password, and a link that replaces the earlier.
     *
     * @throws AccountRefused when the password is too weak (PasswordPolicy)
     */
    public function signUp(string $name, string $email, string $password): void
    {
        $weak = PasswordPolicy::whyWeak($password);
        if (null !== $weak) {
            throw AccountRefused::weakPassword($weak);
        }
        $email = mb_strtolower(trim($email));
        try {
            $this->signUpAs($name, $email, $password);
        } catch (UniqueConstraintViolationException) {
            // Someone signed up with this address at the same moment and was emailed; the answer stays the same.
        }
    }

    private function signUpAs(string $name, string $email, string $password): void
    {
        $this->transaction->run(function () use ($name, $email, $password): void {
            $user = $this->user($email);
            // Hashed whether it is kept or not, so the answer takes as long for an address that has an account.
            $hash = $this->hasher->hashPassword($user ?? new User($email, $name), $password);
            if (null !== $user && $user->isVerified()) {
                $this->sendAfterCommit($this->accountExistsEmail($user));

                return;
            }
            if (null === $user) {
                $user = new User($email, $name, verified: false);
                $this->em->persist($user);
            }
            // Signing up again before verifying starts over: this name and password, and a new link.
            $user->rename($name);
            $user->setPassword($hash);
            $this->em->flush();
            $this->sendAfterCommit($this->verificationEmail($user, $this->tokens->issue(Purpose::VerifyEmail, (string) $user->getId())));
        });
    }

    /**
     * Emails a new verification link, which replaces the earlier one, when this address has a
     * User who has not verified it yet; otherwise nothing happens, and the caller cannot tell.
     */
    public function resendVerification(string $email): void
    {
        $this->transaction->run(function () use ($email): void {
            $user = $this->user(trim($email));
            if (null !== $user && !$user->isVerified()) {
                $this->sendAfterCommit($this->verificationEmail($user, $this->tokens->issue(Purpose::VerifyEmail, (string) $user->getId())));
            }
        });
    }

    /**
     * Emails a link to choose a new password (valid 30 minutes; it replaces an earlier one) when
     * this address has a User; otherwise nothing happens, and the caller cannot tell.
     */
    public function forgotPassword(string $email): void
    {
        $this->transaction->run(function () use ($email): void {
            $user = $this->user(trim($email));
            if (null !== $user) {
                $this->sendAfterCommit($this->resetEmail($user, $this->tokens->issue(Purpose::ResetPassword, (string) $user->getId())));
            }
        });
    }

    /**
     * Gives the User whose password reset link holds this token the new password; the link stops
     * working. The link reached their address, so they count as verified from now on (an open
     * verification link stops working too). Their other sessions end (the password changed).
     *
     * @throws AccountRefused when the password is too weak (the link keeps working), or the link does not work (any more)
     */
    public function resetPassword(string $token, string $password): User
    {
        $weak = PasswordPolicy::whyWeak($password);
        if (null !== $weak) {
            throw AccountRefused::weakPassword($weak);
        }

        return $this->transaction->run(function () use ($token, $password): User {
            try {
                $id = $this->tokens->redeem(Purpose::ResetPassword, $token);
            } catch (TokenRefused $refused) {
                throw $refused->expired ? AccountRefused::expiredResetLink() : AccountRefused::unknownResetLink();
            }
            $user = $this->em->find(User::class, (int) $id) ?? throw AccountRefused::unknownResetLink();
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $user->verify();
            $this->tokens->revoke(Purpose::VerifyEmail, $id);
            $this->em->flush();

            return $user;
        });
    }

    /**
     * Verifies the User whose verification link holds this token; the link stops working.
     *
     * @throws AccountRefused when the link does not work (any more)
     */
    public function verify(string $token): User
    {
        return $this->transaction->run(function () use ($token): User {
            try {
                $id = $this->tokens->redeem(Purpose::VerifyEmail, $token);
            } catch (TokenRefused $refused) {
                throw $refused->expired ? AccountRefused::expiredLink() : AccountRefused::unknownLink();
            }
            $user = $this->em->find(User::class, (int) $id) ?? throw AccountRefused::unknownLink();
            $user->verify();
            $this->em->flush();

            return $user;
        });
    }

    /**
     * Starts a new Workspace with this name, URL slug and colour, whose Owner the User is, with the
     * default Teams and notification settings (WorkspaceDefaults) and no Projects yet.
     *
     * @throws AccountRefused when the User is a Member of a Workspace already, or the URL is taken
     */
    public function startWorkspace(User $user, string $name, string $url, string $c): Member
    {
        try {
            return $this->start($user, $name, $url, $c);
        } catch (UniqueConstraintViolationException) {
            // Another Workspace took the URL at the same moment.
            throw AccountRefused::urlTaken($url);
        }
    }

    private function start(User $user, string $name, string $url, string $c): Member
    {
        return $this->transaction->run(function () use ($user, $name, $url, $c): Member {
            if (null !== $this->members->forUser($user)) {
                throw AccountRefused::hasWorkspace();
            }
            if (null !== $this->em->getRepository(Workspace::class)->findOneBy(['url' => $url])) {
                throw AccountRefused::urlTaken($url);
            }
            $ws = new Workspace($name, $url, $c);
            $owner = new Member(self::newId('m'), $ws, $user->getName(), $user->getEmail(), 'Owner', '', '', '#5A67D8', 'active', 0, '—', $user);
            $owner->keepPersonal('notifPrefs', WorkspaceDefaults::NOTIF_PREFS);
            $this->em->persist($ws);
            $this->em->persist($owner);
            // Team ids are unique across Workspaces, so the new one's are its own; numbered, so that
            // they keep their order (Snapshot lists records by id).
            $stem = self::newId('tm');
            foreach (array_values(WorkspaceDefaults::TEAMS) as $i => $record) {
                $team = new Team($stem.'-'.($i + 1), $ws);
                $team->update($record);
                $this->em->persist($team);
            }
            $this->em->flush();

            return $owner;
        });
    }

    /** A fresh id for a record the server makes, shaped like the frontend's uid(): a prefix and random letters and digits. */
    private static function newId(string $prefix): string
    {
        return $prefix.bin2hex(random_bytes(6));
    }

    /** The User with this email address, in any case. */
    private function user(string $email): ?User
    {
        return $this->em->createQuery('SELECT u FROM '.User::class.' u WHERE LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower($email))
            ->getOneOrNullResult();
    }

    private function sendAfterCommit(Email $email): void
    {
        $this->transaction->afterCommit(function () use ($email): void {
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('The email "{subject}" could not be sent: {reason}', ['subject' => $email->getSubject(), 'reason' => $e->getMessage()]);
            }
        });
    }

    private function verificationEmail(User $user, string $token): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('Verify your email address for Gr8r Studio')
            ->text(\sprintf(
                "Hi %s,\n\nConfirm that this is your email address to finish signing up for Gr8r Studio:\n%s/#verify/%s\n\nThe link works once and expires in 24 hours. If you didn't sign up, you can ignore this email.\n",
                $user->getName(), rtrim($this->frontendUrl, '/'), $token,
            ));
    }

    private function resetEmail(User $user, string $token): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('Reset your Gr8r Studio password')
            ->text(\sprintf(
                "Hi %s,\n\nChoose a new password for your Gr8r Studio account here:\n%s/#reset/%s\n\nThe link works once and expires in 30 minutes. If you didn't ask for it, you can ignore this email: your password has not changed.\n",
                $user->getName(), rtrim($this->frontendUrl, '/'), $token,
            ));
    }

    /** To the owner of an address someone signed up with that has an account already. */
    private function accountExistsEmail(User $user): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('You already have a Gr8r Studio account')
            ->text(\sprintf(
                "Hi %s,\n\nSomeone, hopefully you, tried to sign up for Gr8r Studio with this email address, which has an account already. Sign in here instead:\n%s/#login\n\nIf you forgot your password, choose \"Forgot password?\" there. If it wasn't you, you can ignore this email: your account has not changed.\n",
                $user->getName(), rtrim($this->frontendUrl, '/'),
            ));
    }
}
