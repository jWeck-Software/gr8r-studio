<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What is left of a deleted Comment, so that undo can put it back as its author's: the frontend
 * restores a deleted Task or Project with its Comments by putting them again, and a put of an
 * unknown id would otherwise be a new Comment by whoever undid. Never shown in the snapshot.
 *
 * `task` is a plain id: the Task is usually deleted with its Comments and comes back first.
 */
#[ORM\Entity]
class DeletedComment
{
    /** @param array{by: string, at: int, text: string, re: array<string, list<string>>} $record */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        #[ORM\Column(length: 44)]
        private string $task,
        /** The Comment's author, time, text and Reactions, as they were. */
        #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
        private array $record,
    ) {
    }

    public static function of(Comment $comment): self
    {
        $record = $comment->toRecord();

        return new self($comment->getId(), $comment->getWorkspace(), $record['task'], ['by' => $record['by'], 'at' => $record['at'], 'text' => $record['text'], 're' => (array) $record['re']]);
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    /** The id of the Task the Comment was on. */
    public function getTask(): string
    {
        return $this->task;
    }

    /** The Comment as it was, on its Task again. */
    public function restore(Task $task): Comment
    {
        $comment = new Comment($this->id, $this->workspace);
        $comment->update($this->record, $task);

        return $comment;
    }
}
