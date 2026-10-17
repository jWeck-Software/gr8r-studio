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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A User's password: choosing a new one with a link by email (App\Token\Tokens,
 * Purpose::ResetPassword, valid for 30 minutes), and changing it while signed in. The link's email
 * goes out once it has been stored (Emails::sendAfterCommit()); one that cannot be sent is logged,
 * and the User can ask for it again.
 */
final class Passwords
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
     * Emails a link to choose a new password (valid 30 minutes; it replaces an earlier one) when
     * this address has a User; otherwise nothing happens, and the caller cannot tell.
     */
    public function forgotPassword(string $email): void
    {
        $this->transaction->run(function () use ($email): void {
            $user = $this->users->withEmail(trim($email));
            if (null !== $user) {
                $this->emails->sendAfterCommit($this->resetEmail($user, $this->tokens->issue(Purpose::ResetPassword, (string) $user->getId())));
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
     * Gives the signed-in User a new password, when the current one is theirs. Their other sessions
     * end (the password changed), and an open password reset link stops working.
     *
     * @throws AccountRefused when the current password is wrong, or the new one too weak
     */
    public function changePassword(User $user, string $current, string $new): void
    {
        if (!$this->hasher->isPasswordValid($user, $current)) {
            throw AccountRefused::wrongPassword();
        }
        $weak = PasswordPolicy::whyWeak($new);
        if (null !== $weak) {
            throw AccountRefused::weakPassword($weak);
        }
        $this->transaction->run(function () use ($user, $new): void {
            $user->setPassword($this->hasher->hashPassword($user, $new));
            $this->tokens->revoke(Purpose::ResetPassword, (string) $user->getId());
            $this->em->flush();
        });
    }

    private function resetEmail(User $user, string $token): Email
    {
        return (new Email())
            ->to($user->getEmail())
            ->subject('Reset your Gr8r Studio password')
            ->text(\sprintf(
                "Hi %s,\n\nChoose a new password for your Gr8r Studio account here:\n%s\n\nThe link works once and expires in %s. If you didn't ask for it, you can ignore this email: your password has not changed.\n",
                $user->getName(), $this->emails->link(Purpose::ResetPassword, $token), Emails::lifetime(Purpose::ResetPassword),
            ));
    }
}
