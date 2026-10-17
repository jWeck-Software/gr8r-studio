<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A person who can sign in, identified by an email address and a password. Whoever signs up is
 * unverified until they open the link emailed to that address; until then they cannot sign in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private string $password = '';

    public function __construct(
        // Stored lowercased; binary, so that addresses differing only in accents stay distinct.
        #[ORM\Column(length: 180, unique: true, options: ['collation' => 'utf8mb4_bin'])]
        private string $email,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(options: ['default' => true])]
        private bool $verified = true,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    /** Whether they proved the email address is theirs: by the link of signing up, or of an Invite. */
    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function verify(): void
    {
        $this->verified = true;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
