<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The open link of one purpose for one subject, such as the Invite link of a Member: only the hash
 * of its token is kept, with when it expires. A subject has at most one open link per purpose.
 * Kept by App\Token\Tokens, the one place that issues and redeems them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'one_time_token')]
class Token
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 20)]
        private string $purpose,
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $subject,
        #[ORM\Column(length: 64, unique: true)]
        private string $hash,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** A new link replaces the old one, which stops working. */
    public function renew(string $hash, \DateTimeImmutable $expiresAt): void
    {
        $this->hash = $hash;
        $this->expiresAt = $expiresAt;
    }
}
