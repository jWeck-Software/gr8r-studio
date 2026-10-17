<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What is left of a deleted Task, so that undo, which puts the Task again, is not taken for a new
 * Task: the put is compared with the Task as it was, and assigning it notifies nobody again
 * (ADR 0003). Never shown in the snapshot; gone once the Task is back.
 */
#[ORM\Entity]
class DeletedTask
{
    /** @param array<string, mixed> $record */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        /** The Task as it was, in the frontend's shape. */
        #[ORM\Column(type: Types::JSON)]
        private array $record,
    ) {
    }

    public static function of(Task $task): self
    {
        return new self($task->getId(), $task->getWorkspace(), $task->toRecord());
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    /** @return array<string, mixed> the Task as it was when it was deleted */
    public function getRecord(): array
    {
        return $this->record;
    }
}
