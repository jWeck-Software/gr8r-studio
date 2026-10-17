<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A User's place in one Workspace, carrying a Role, a Team and a title.
 * A Member can exist before its User has accepted the Invite, so the User is optional.
 */
#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
    /** @var array<string, list<string>> the ids this Member starred, by collection ("projects", "tasks") */
    #[ORM\Column(type: Types::JSON)]
    private array $favorites = [];

    /**
     * @var array<string, mixed> the Member's own singletons of the frontend, by type ("prefs", "views",
     *                           "notifPrefs", "recentSearches"), as App\Sync\Handler\PersonalSetting stored them
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $personal = [];

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

    /** A Guest only reads the Projects they belong to and comments on them. */
    public function isGuest(): bool
    {
        return 'Guest' === $this->role;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** Invited and not joined yet: the Member has no User until they accept the Invite. */
    public function isInvited(): bool
    {
        return 'invited' === $this->status;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * What a Member's put may change; the rest (email, status, last active, time zone) is the server's.
     * A new name is their User's too, the one they sign in with.
     *
     * @param array{name: string, title: string, c: string, role: string, team: string} $changes
     */
    public function update(array $changes): void
    {
        if ($this->name !== $changes['name']) {
            $this->user?->rename($changes['name']);
        }
        $this->name = $changes['name'];
        $this->title = $changes['title'];
        $this->c = $changes['c'];
        $this->role = $changes['role'];
        $this->team = $changes['team'];
    }

    /** The invited Member accepted their Invite as this User, under this name: they are active from now on. */
    public function join(User $user, string $name): void
    {
        $this->user = $user;
        $this->name = $name;
        $this->status = 'active';
        $this->last = 0;
    }

    /** A deleted Team leaves its people and Projects without one (''). */
    public function leaveTeam(string $teamId): void
    {
        if ($this->team === $teamId) {
            $this->team = '';
        }
    }

    /** Stars or unstars a record of a collection, such as a Project in "projects", for this Member only. */
    public function star(string $collection, string $id, bool $starred): void
    {
        $ids = array_values(array_diff($this->favorites[$collection] ?? [], [$id]));
        if ($starred) {
            $ids[] = $id;
        }
        $this->favorites = [...$this->favorites, $collection => $ids];
    }

    /** @return list<string> the ids of the records of a collection this Member starred */
    public function starred(string $collection): array
    {
        return $this->favorites[$collection] ?? [];
    }

    /** Keeps one of the Member's own settings, such as their Preferences ("prefs"), replacing what was there. */
    public function keepPersonal(string $type, mixed $value): void
    {
        $this->personal = [...$this->personal, $type => $value];
    }

    /** @return mixed the Member's own setting of this type, null when they never stored one */
    public function personal(string $type): mixed
    {
        return $this->personal[$type] ?? null;
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
