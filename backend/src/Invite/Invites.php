<?php

namespace App\Invite;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
use App\Entity\User;
use App\Persistence\Transaction;
use App\Repository\MemberRepository;
use App\Security\PasswordPolicy;
use App\Token\Purpose;
use App\Token\TokenRefused;
use App\Token\Tokens;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invites: the link an invited Member gets by email, and accepting it. A link holds a one-time
 * token (App\Token\Tokens, Purpose::Invite) that expires after a week.
 */
final readonly class Invites
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Tokens $tokens,
        private Transaction $transaction,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $hasher,
        private MemberRepository $members,
        private WorkspaceAccess $access,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    /**
     * Emails the invited Member a link to join, from the actor, once the running transaction has
     * committed (Transaction::afterCommit()). The batch is stored by then, so an email that cannot
     * be sent is logged rather than failing it; the actor can send the Invite again.
     */
    public function send(Member $actor, Member $invited): void
    {
        $email = $this->issue($actor, $invited);
        $this->transaction->afterCommit(function () use ($email, $invited): void {
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('The Invite email to {member} could not be sent: {reason}', ['member' => $invited->getId(), 'reason' => $e->getMessage()]);
            }
        });
    }

    /**
     * Sends the Invite of an invited Member of the actor's Workspace again, with a fresh link;
     * the earlier link stops working. The email goes out once the new link is stored.
     *
     * @throws InviteRefused when there is no such Member, they joined already, or the actor may
     *                       not invite them (WorkspaceAccess::whyNotInvite())
     */
    public function resend(Member $actor, string $memberId): void
    {
        $email = $this->transaction->run(function () use ($actor, $memberId): Email {
            $member = $this->em->find(Member::class, $memberId);
            if (null === $member || $member->getWorkspace() !== $actor->getWorkspace()) {
                throw InviteRefused::noSuchMember();
            }
            $refusal = $this->access->whyNotInvite($actor, $member->getRole());
            if (null !== $refusal) {
                throw InviteRefused::forbidden($refusal);
            }
            if (!$member->isInvited()) {
                throw InviteRefused::joinedAlready($member->getName());
            }
            $email = $this->issue($actor, $member);
            $this->em->flush();

            return $email;
        });
        $this->mailer->send($email);
    }

    /**
     * What the page behind the link shows before the invited person joins: their email address,
     * the name they were invited under, the Workspace's name, and whether the address has an
     * account already (then they join with its password).
     *
     * @return array{email: string, name: string, workspace: string, account: bool}
     *
     * @throws InviteRefused when the link does not lead into the Workspace (any more)
     */
    public function describe(string $token): array
    {
        $member = $this->open($token);

        return [
            'email' => $member->getEmail(),
            'name' => $member->getName(),
            'workspace' => $member->getWorkspace()->getName(),
            'account' => null !== $this->em->getRepository(User::class)->findOneBy(['email' => $member->getEmail()]),
        ];
    }

    /**
     * Accepts the Invite whose link holds this token: the invited person becomes a User with this
     * name and password, and their Member is active under that name. The link stops working.
     * When their email address has a User already, the password has to be that User's; it stays,
     * and that User counts as verified from now on.
     *
     * @throws InviteRefused when the link does not lead into the Workspace (any more)
     */
    public function accept(string $token, string $name, string $password): User
    {
        return $this->transaction->run(function () use ($token, $name, $password): User {
            $member = $this->open($token);
            $user = $this->user($member->getEmail(), $name, $password);
            $member->join($user, $name);
            $this->tokens->redeem(Purpose::Invite, $token);
            $this->em->flush();

            return $user;
        });
    }

    /** A removed Member's Invite goes with them: the link they were sent stops working. */
    public function forget(Member $member): void
    {
        $this->tokens->revoke(Purpose::Invite, $member->getId());
    }

    /** The invited Member whose open Invite link holds this token. */
    private function open(string $token): Member
    {
        try {
            $id = $this->tokens->subjectOf(Purpose::Invite, $token);
        } catch (TokenRefused $refused) {
            throw $refused->expired ? InviteRefused::expired() : InviteRefused::unknown();
        }
        $member = $this->em->find(Member::class, $id);
        if (null === $member || !$member->isInvited()) {
            throw InviteRefused::unknown();
        }

        return $member;
    }

    /**
     * The User the invited person joins as: the one their email address has already (a removed
     * Member's User stays), when the password is theirs and they are in no other Workspace, or
     * else a new one with this password.
     */
    private function user(string $email, string $name, string $password): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $user) {
            if (!$this->hasher->isPasswordValid($user, $password)) {
                throw InviteRefused::notTheirPassword($email);
            }
            if (null !== $this->members->forUser($user)) {
                throw InviteRefused::inAnotherWorkspace($email);
            }
            $user->rename($name);
            // The link reached the address: one that signed up and never verified is verified now.
            $user->verify();

            return $user;
        }
        $weak = PasswordPolicy::whyWeak($password);
        if (null !== $weak) {
            throw InviteRefused::weakPassword($weak);
        }
        $user = new User($email, $name);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }

    /** A fresh link for the invited Member, as the email that brings it; an earlier link stops working. */
    private function issue(Member $actor, Member $invited): Email
    {
        return $this->email($actor, $invited, $this->tokens->issue(Purpose::Invite, $invited->getId()));
    }

    private function email(Member $actor, Member $invited, string $token): Email
    {
        $workspace = $invited->getWorkspace()->getName();

        return (new Email())
            ->to($invited->getEmail())
            ->subject(\sprintf('%s invited you to %s', $actor->getName(), $workspace))
            ->text(\sprintf(
                "%s invited you to join %s on Gr8r Studio.\n\nChoose your password and join here:\n%s/#join/%s\n\nThe link works once and expires in 7 days. If you didn't expect this invite, you can ignore this email.\n",
                $actor->getName(), $workspace, rtrim($this->frontendUrl, '/'), $token,
            ));
    }
}
