<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The open Invite of an invited Member: the hash of the one link that accepts it, and when that
 * link expires. Sending the Invite again replaces both; accepting it removes it.
 */
#[ORM\Entity]
class Invite
{
    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Member $member,
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** A new link replaces the old one, which stops working. */
    public function renew(string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
    }
}
