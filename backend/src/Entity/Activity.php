<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entry of the Workspace's record of who changed what, and when. Append-only.
 *
 * `task` and `project` are plain ids, not references: an entry outlives the Task or Project it
 * is about, and the snapshot decides who sees it.
 */
#[ORM\Entity]
#[ORM\Index(name: 'activity_project', columns: ['project'])]
class Activity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        /** The Member id of whoever made the change. */
        #[ORM\Column(name: 'author', length: 44)]
        private string $by,
        #[ORM\Column(type: Types::TEXT)]
        private string $verb,
        #[ORM\Column(length: 44, nullable: true)]
        private ?string $task,
        #[ORM\Column(length: 44, nullable: true)]
        private ?string $project,
        /** Epoch milliseconds. */
        #[ORM\Column(type: Types::BIGINT)]
        private int $at,
        #[ORM\Column(type: Types::TEXT)]
        private string $extra,
    ) {
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    /** @return array<string, mixed> the entry in the frontend's shape */
    public function toRecord(): array
    {
        return [
            'id' => $this->id,
            'by' => $this->by,
            'verb' => $this->verb,
            'task' => $this->task,
            'project' => $this->project,
            'at' => $this->at,
            'extra' => $this->extra,
        ];
    }
}
