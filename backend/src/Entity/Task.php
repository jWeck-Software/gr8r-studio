<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One piece of work inside a Project. Subtasks, labels, Dependencies and Attachments
 * are value-like and stored with the Task.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'task_key_per_project', columns: ['project_id', 'key'])]
class Task
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 30)]
    private string $key = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $title = '';

    #[ORM\Column(length: 20)]
    private string $status = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $assignee = null;

    #[ORM\Column(length: 20)]
    private string $priority = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $due = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $start = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $labels = [];

    /** @var list<array{id: string, title: string, done: bool}> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $subtasks = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $attachments = [];

    /** @var list<string> ids of the Tasks this Task depends on */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $deps = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $estimate = null;

    /** Epoch milliseconds. */
    #[ORM\Column(type: Types::BIGINT)]
    private int $created = 0;

    /** Epoch milliseconds. */
    #[ORM\Column(type: Types::BIGINT)]
    private int $updated = 0;

    #[ORM\Column]
    private float $sortOrder = 0;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $recur = null;

    /** Epoch milliseconds; null when the record carries no `completedAt` key. */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $completedAt = null;

    /** Null when the record carries no `prevStatus` key. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $prevStatus = null;

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

    /**
     * @param array<string, mixed> $record a valid Task in the frontend's shape; `project` and
     *                                     `assignee` are given as the resolved Project and Member
     */
    public function update(array $record, Project $project, ?Member $assignee): void
    {
        $this->project = $project;
        $this->assignee = $assignee;
        $this->key = $record['key'];
        $this->title = $record['title'];
        $this->status = $record['status'];
        $this->priority = $record['priority'];
        $this->due = null === $record['due'] ? null : new \DateTimeImmutable($record['due']);
        $this->start = null === $record['start'] ? null : new \DateTimeImmutable($record['start']);
        $this->labels = $record['labels'];
        $this->subtasks = $record['subtasks'];
        $this->attachments = $record['attachments'];
        $this->deps = $record['deps'];
        $this->description = $record['desc'];
        $this->estimate = $record['estimate'];
        $this->created = $record['created'];
        $this->updated = $record['updated'];
        $this->sortOrder = $record['order'];
        $this->recur = $record['recur'];
        $this->completedAt = $record['completedAt'] ?? null;
        $this->prevStatus = $record['prevStatus'] ?? null;
        $this->archived = $record['archived'] ?? null;
        $this->archivedAt = $record['archivedAt'] ?? null;
    }

    /** @return array<string, mixed> the Task in the frontend's shape */
    public function toRecord(): array
    {
        $order = $this->sortOrder;
        $record = [
            'id' => $this->id,
            'key' => $this->key,
            'project' => $this->project->getId(),
            'title' => $this->title,
            'status' => $this->status,
            'assignee' => $this->assignee?->getId(),
            'priority' => $this->priority,
            'due' => $this->due?->format('Y-m-d'),
            'start' => $this->start?->format('Y-m-d'),
            'labels' => $this->labels,
            'subtasks' => $this->subtasks,
            'attachments' => $this->attachments,
            'deps' => $this->deps,
            'desc' => $this->description,
            'estimate' => $this->estimate,
            'created' => $this->created,
            'updated' => $this->updated,
            'order' => floor($order) === $order && abs($order) < 2 ** 53 ? (int) $order : $order,
            'recur' => $this->recur,
        ];
        if (null !== $this->completedAt) {
            $record['completedAt'] = $this->completedAt;
        }
        if (null !== $this->prevStatus) {
            $record['prevStatus'] = $this->prevStatus;
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
