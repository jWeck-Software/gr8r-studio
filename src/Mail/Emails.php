<?php

namespace App\Mail;

use App\Persistence\Transaction;
use App\Token\Purpose;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * The app's emails: the links they carry into the frontend (APP_FRONTEND_URL), how long a
 * one-time link works in words (Purpose::validFor()), and sending them. Most go out once what
 * they are about has been stored (sendAfterCommit()); one that cannot be sent then is logged, and
 * the User can ask for it again.
 */
final class Emails
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Transaction $transaction,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    /** The frontend's page for this one-time link, such as `<APP_FRONTEND_URL>/#verify/<token>`. */
    public function link(Purpose $purpose, string $token): string
    {
        return $this->page(match ($purpose) {
            Purpose::Invite => 'join',
            Purpose::VerifyEmail => 'verify',
            Purpose::ResetPassword => 'reset',
        }.'/'.$token);
    }

    /** A page of the frontend, such as `<APP_FRONTEND_URL>/#login`. */
    public function page(string $fragment): string
    {
        return rtrim($this->frontendUrl, '/').'/#'.$fragment;
    }

    /** How long a link for this purpose works, in words: "7 days", "24 hours", "30 minutes". */
    public static function lifetime(Purpose $purpose): string
    {
        return ltrim($purpose->validFor(), '+');
    }

    /**
     * Sends the email once the running transaction has committed (Transaction::afterCommit()).
     * What it is about is stored by then, so one that cannot be sent is logged rather than failing,
     * with this message and context (plus `subject` and `reason`).
     *
     * @param array<string, mixed> $context
     */
    public function sendAfterCommit(Email $email, string $failure = 'The email "{subject}" could not be sent: {reason}', array $context = []): void
    {
        $this->transaction->afterCommit(function () use ($email, $failure, $context): void {
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error($failure, $context + ['subject' => $email->getSubject(), 'reason' => $e->getMessage()]);
            }
        });
    }

    /**
     * Sends the email now: one that cannot be sent reaches the caller.
     *
     * @throws TransportExceptionInterface
     */
    public function send(Email $email): void
    {
        $this->mailer->send($email);
    }
}
