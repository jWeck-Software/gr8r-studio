<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A User's place in one Workspace, carrying a Role, a Team and a title.
 * A Member can exist before its User has accepted the Invite, so the User is optional.
 */
#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 20)]
        private string $role,
        #[ORM\Column(length: 44)]
        private string $team,
        #[ORM\Column(length: 120)]
        private string $title,
        #[ORM\Column(length: 20)]
        private string $c,
        #[ORM\Column(length: 20)]
        private string $status,
        #[ORM\Column(nullable: true)]
        private ?int $last,
        #[ORM\Column(length: 80)]
        private string $tz,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /** @return array<string, mixed> the Member in the frontend's shape */
    public function toRecord(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'team' => $this->team,
            'title' => $this->title,
            'c' => $this->c,
            'status' => $this->status,
            'last' => $this->last,
            'tz' => $this->tz,
        ];
    }
}
