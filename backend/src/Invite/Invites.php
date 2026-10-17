<?php

namespace App\Invite;

use App\Entity\Invite;
use App\Entity\Member;
use App\Entity\User;
use App\Persistence\Transaction;
use App\Repository\MemberRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invites: the link an invited Member gets by email, and accepting it. A link holds a random
 * single-use token; only its hash is stored, and it expires after a week.
 */
final readonly class Invites
{
    private const string VALID_FOR = '+7 days';

    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private ClockInterface $clock,
        private Transaction $transaction,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $hasher,
        private MemberRepository $members,
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
     * Accepts the Invite whose link holds this token: the invited person becomes a User with this
     * name and password, and their Member is active under that name. The link stops working.
     * When their email address has a User already, the password has to be that User's; it stays.
     *
     * @throws InviteRefused when the link does not lead into the Workspace (any more)
     */
    public function accept(string $token, string $name, string $password): User
    {
        return $this->transaction->run(function () use ($token, $name, $password): User {
            $invite = $this->em->getRepository(Invite::class)->findOneBy(['tokenHash' => hash('sha256', $token)]) ?? throw InviteRefused::unknown();
            if ($invite->getExpiresAt() <= $this->clock->now()) {
                throw InviteRefused::expired();
            }
            $member = $invite->getMember();
            $user = $this->user($member->getEmail(), $name, $password);
            $member->join($user, $name);
            $this->em->remove($invite);
            $this->em->flush();

            return $user;
        });
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
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $expiresAt = $this->clock->now()->modify(self::VALID_FOR);
        $invite = $this->em->find(Invite::class, $invited->getId());
        if (null === $invite) {
            $this->em->persist(new Invite($invited, $hash, $expiresAt));
        } else {
            $invite->renew($hash, $expiresAt);
        }

        return $this->email($actor, $invited, $token);
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
