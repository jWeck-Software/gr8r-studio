<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A message to one Member that something concerning them happened. Made by the server only
 * (ADR 0003); its recipient may only mark it read or unread.
 *
 * `task` and `project` are plain ids, not references, like Activity's: a Notification about a
 * deleted Task comes back when the Task is restored, and the snapshot decides who sees it.
 */
#[ORM\Entity]
class Notification
{
    #[ORM\Column(name: 'is_read')]
    private bool $read = false;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Member $recipient,
        /** mention, assign, comment or update */
        #[ORM\Column(length: 20)]
        private string $type,
        /** The Member id of whoever caused it; null for the Workspace's own reminders. */
        #[ORM\Column(name: 'author', length: 44, nullable: true)]
        private ?string $by,
        #[ORM\Column(length: 44, nullable: true)]
        private ?string $task,
        #[ORM\Column(length: 44, nullable: true)]
        private ?string $project,
        #[ORM\Column(type: Types::TEXT)]
        private string $text,
        #[ORM\Column(type: Types::TEXT)]
        private string $snippet,
        /** Epoch milliseconds. */
        #[ORM\Column(type: Types::BIGINT)]
        private int $at,
    ) {
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getRecipient(): Member
    {
        return $this->recipient;
    }

    public function markRead(bool $read): void
    {
        $this->read = $read;
    }

    /** @return array<string, mixed> the Notification in the frontend's shape; `task` and `project` only when set */
    public function toRecord(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'by' => $this->by,
            ...(null === $this->task ? [] : ['task' => $this->task]),
            ...(null === $this->project ? [] : ['project' => $this->project]),
            'text' => $this->text,
            'snippet' => $this->snippet,
            'at' => $this->at,
            'read' => $this->read,
        ];
    }
}
