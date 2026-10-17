<?php

namespace App\Account;

use App\Entity\User;
use App\Mail\Emails;
use App\Persistence\Transaction;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Token\Purpose;
use App\Token\TokenRefused;
use App\Token\Tokens;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Signing up, and verifying the email address with the link that is sent (App\Token\Tokens,
 * Purpose::VerifyEmail, valid for 24 hours). Emails go out once what they are about has been
 * stored (Emails::sendAfterCommit()); one that cannot be sent is logged, and the User can ask for
 * it again.
 */
final class SignUps
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly Emails $emails,
        private readonly Tokens $tokens,
        private readonly Transaction $transaction,
        private readonly UserPasswordHasherInterface $hasher,
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
            $user = $this->users->withEmail($email);
            // Hashed whether it is kept or not, so the answer takes as long for an address that has an account.
            $hash = $this->hasher->hashPassword($user ?? new User($email, $name), $password);
            if (null !== $user && $user->isVerified()) {
                $this->emails->sendAfterCommit($this->accountExistsEmail($user));

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
            $this->emails->sendAfterCommit($this->verificationEmail($user, $this->tokens->issue(Purpose::VerifyEmail, (string) $user->getId())));
        });
    }

    /**
     * Emails a new verification link, which replaces the earlier one, when this address has a
     * User who has not verified it yet; otherwise nothing happens, and the caller cannot tell.
     */
    public function resendVerification(string $email): void
    {
        $this->transaction->run(function () use ($email): void {
            $user = $this->users->withEmail(trim($email));
            if (null !== $user && !$user->isVerified()) {
                $this->emails->sendAfterCommit($this->verificationEmail($user, $this->tokens->issue(Purpose::VerifyEmail, (string) $user->getId())));
            }
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

    private function verificationEmail(User $user, string $token): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('Verify your email address for Gr8r Studio')
            ->text(\sprintf(
                "Hi %s,\n\nConfirm that this is your email address to finish signing up for Gr8r Studio:\n%s\n\nThe link works once and expires in %s. If you didn't sign up, you can ignore this email.\n",
                $user->getName(), $this->emails->link(Purpose::VerifyEmail, $token), Emails::lifetime(Purpose::VerifyEmail),
            ));
    }

    /** To the owner of an address someone signed up with that has an account already. */
    private function accountExistsEmail(User $user): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('You already have a Gr8r Studio account')
            ->text(\sprintf(
                "Hi %s,\n\nSomeone, hopefully you, tried to sign up for Gr8r Studio with this email address, which has an account already. Sign in here instead:\n%s\n\nIf you forgot your password, choose \"Forgot password?\" there. If it wasn't you, you can ignore this email: your account has not changed.\n",
                $user->getName(), $this->emails->page('login'),
            ));
    }
}
