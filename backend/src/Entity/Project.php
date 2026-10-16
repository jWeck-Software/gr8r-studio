<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A body of work with a key, a lead, a due date and its own Tasks. */
#[ORM\Entity]
class Project
{
    #[ORM\Column(length: 20)]
    private string $key = '';

    #[ORM\Column(length: 200)]
    private string $name = '';

    #[ORM\Column(length: 60)]
    private string $icon = '';

    #[ORM\Column(length: 30)]
    private string $color = '';

    #[ORM\Column(length: 20)]
    private string $status = '';

    #[ORM\Column(length: 44)]
    private string $team = '';

    #[ORM\Column(length: 44, nullable: true)]
    private ?string $lead = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $due = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $start = null;

    #[ORM\Column]
    private bool $fav = false;

    /** @var list<string> Member ids */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $members = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    /** @var list<array{name: string, date: string}> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $milestones = [];

    #[ORM\Column(nullable: true)]
    private ?int $last = null;

    /** Null when the record carries no `private` key. */
    #[ORM\Column(nullable: true)]
    private ?bool $isPrivate = null;

    /** Epoch milliseconds; null when the record carries no `archivedAt` or it is null. */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $archivedAt = null;

    /** Null when the record carries no `archived` key. */
    #[ORM\Column(nullable: true)]
    private ?bool $archived = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
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

    /** @param array<string, mixed> $record a Project in the frontend's shape */
    public function update(array $record): void
    {
        $this->key = $record['key'];
        $this->name = $record['name'];
        $this->icon = $record['icon'];
        $this->color = $record['color'];
        $this->status = $record['status'];
        $this->team = $record['team'];
        $this->lead = $record['lead'];
        $this->due = null === $record['due'] ? null : new \DateTimeImmutable($record['due']);
        $this->start = null === $record['start'] ? null : new \DateTimeImmutable($record['start']);
        $this->fav = $record['fav'];
        $this->members = $record['members'];
        $this->description = $record['desc'];
        $this->milestones = $record['milestones'];
        $this->last = $record['last'];
        $this->isPrivate = $record['private'] ?? null;
        $this->archived = $record['archived'] ?? null;
        $this->archivedAt = $record['archivedAt'] ?? null;
    }

    /** @return array<string, mixed> the Project in the frontend's shape */
    public function toRecord(): array
    {
        $record = [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'status' => $this->status,
            'team' => $this->team,
            'lead' => $this->lead,
            'due' => $this->due?->format('Y-m-d'),
            'start' => $this->start?->format('Y-m-d'),
            'fav' => $this->fav,
            'members' => $this->members,
            'desc' => $this->description,
            'milestones' => $this->milestones,
            'last' => $this->last,
        ];
        if (null !== $this->isPrivate) {
            $record['private'] = $this->isPrivate;
        }
        if (null !== $this->archived) {
            $record['archived'] = $this->archived;
        }
        if (null !== $this->archivedAt) {
            $record['archivedAt'] = $this->archivedAt;
        }

        return $record;
    }
}
