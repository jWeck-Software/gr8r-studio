<?php

namespace App\Token;

use App\Entity\Token;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * One-time links, such as an Invite's or an email verification's: issue() gives the token to put in
 * a link, redeem() gives back whom it was issued for and uses it up. A token is 32 random bytes as
 * URL-safe base64 (43 characters); only its SHA-256 hash is stored. Each subject has at most one open
 * link per purpose: issuing another replaces it. A link works until Purpose::validFor() has passed;
 * an expired one keeps answering "expired" rather than "unknown".
 *
 * Works in the caller's unit of work and transaction: nothing here flushes.
 */
final readonly class Tokens
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    /** A fresh token for the subject's link; the subject's earlier link of this purpose stops working. */
    public function issue(Purpose $purpose, string $subject): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $expiresAt = $this->clock->now()->modify($purpose->validFor());
        $open = $this->em->find(Token::class, ['purpose' => $purpose->value, 'subject' => $subject]);
        if (null === $open) {
            $this->em->persist(new Token($purpose->value, $subject, $hash, $expiresAt));
        } else {
            $open->renew($hash, $expiresAt);
        }

        return $token;
    }

    /**
     * Whom the link holding this token was issued for; it keeps working.
     *
     * @throws TokenRefused
     */
    public function subjectOf(Purpose $purpose, string $token): string
    {
        return $this->open($purpose, $token)->getSubject();
    }

    /**
     * Whom the link holding this token was issued for; it stops working once the caller's unit of work is flushed.
     *
     * @throws TokenRefused
     */
    public function redeem(Purpose $purpose, string $token): string
    {
        $open = $this->open($purpose, $token);
        $this->em->remove($open);

        return $open->getSubject();
    }

    /** The subject's open link of this purpose, if any, stops working. */
    public function revoke(Purpose $purpose, string $subject): void
    {
        $open = $this->em->find(Token::class, ['purpose' => $purpose->value, 'subject' => $subject]);
        if (null !== $open) {
            $this->em->remove($open);
        }
    }

    private function open(Purpose $purpose, string $token): Token
    {
        $open = $this->em->getRepository(Token::class)->findOneBy(['purpose' => $purpose->value, 'hash' => hash('sha256', $token)]) ?? throw TokenRefused::unknown();
        if ($open->getExpiresAt() <= $this->clock->now()) {
            throw TokenRefused::expired();
        }

        return $open;
    }
}
