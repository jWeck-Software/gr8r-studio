<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A message a Member writes on a Task. Reactions are value-like and stored with it:
 * emoji => ids of the Members who reacted.
 */
#[ORM\Entity]
class Comment
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    /** The author's Member id. */
    #[ORM\Column(name: 'author', length: 44)]
    private string $by = '';

    /** Epoch milliseconds. */
    #[ORM\Column(type: Types::BIGINT)]
    private int $at = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $text = '';

    /** @var array<string, list<string>> */
    #[ORM\Column(type: Types::JSON)]
    private array $re = [];

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

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getBy(): string
    {
        return $this->by;
    }

    /**
     * @param array{by: string, at: int, text: string, re: array<string, list<string>>} $record a valid Comment in the
     *                                                                                          frontend's shape; `task`
     *                                                                                          is given as the Task
     */
    public function update(array $record, Task $task): void
    {
        $this->task = $task;
        $this->by = $record['by'];
        $this->at = $record['at'];
        $this->text = $record['text'];
        $this->re = $record['re'];
    }

    /** @return array<string, mixed> the Comment in the frontend's shape */
    public function toRecord(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task->getId(),
            'by' => $this->by,
            'at' => $this->at,
            'text' => $this->text,
            // An object even when empty or when an emoji key looks like a number.
            're' => (object) $this->re,
        ];
    }
}
