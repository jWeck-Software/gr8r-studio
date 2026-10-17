<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A document uploaded to a Project, optionally attached to one of its Tasks (an Attachment is a
 * File as seen from its Task). Its content is a blob kept by App\File\Files, not in the database.
 */
#[ORM\Entity]
class File
{
    #[ORM\Column(length: 20)]
    private string $type;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Task $task,
        #[ORM\Column(length: 255)]
        private string $name,
        /** The display string the frontend shows, e.g. "4.2 MB" (fsize()). */
        #[ORM\Column(length: 20)]
        private string $size,
        /** The uploader's Member id. */
        #[ORM\Column(name: 'uploader', length: 44)]
        private string $by,
        /** Epoch milliseconds. */
        #[ORM\Column(type: Types::BIGINT)]
        private int $at,
    ) {
        $this->type = self::typeOf($name);
    }

    /** The frontend's fileType(): the kind of File its name's extension says it is. */
    public static function typeOf(string $name): string
    {
        $dot = strrpos($name, '.');
        $ext = strtolower(false === $dot ? $name : substr($name, $dot + 1));

        return match (true) {
            'fig' === $ext => 'fig',
            'pdf' === $ext => 'pdf',
            \in_array($ext, ['zip', 'rar', '7z'], true) => 'zip',
            \in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'heic'], true) => 'img',
            \in_array($ext, ['xlsx', 'xls', 'csv', 'numbers'], true) => 'sheet',
            \in_array($ext, ['doc', 'docx', 'txt', 'md', 'pages', 'rtf'], true) => 'doc',
            \in_array($ext, ['js', 'ts', 'json', 'html', 'css', 'py'], true) => 'code',
            default => 'other',
        };
    }

    /** The frontend's fsize(): "512 B", "2 KB", "4.2 MB". */
    public static function sizeOf(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes.' B',
            $bytes < 1048576 => round($bytes / 1024).' KB',
            // toFixed(1) rounds halves up, like round(); sprintf() keeps the binary value's digits
            default => number_format(round($bytes / 1048576, 1), 1, '.', '').' MB',
        };
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->type = self::typeOf($name);
    }

    /** Files attached to a Task are in the Task's Project, also after it moved. */
    public function moveTo(Project $project): void
    {
        $this->project = $project;
    }

    /** A copy with the frontend's " copy" before the extension, uploaded by `$by` at `$at`. */
    public function copy(string $id, string $by, int $at): self
    {
        return new self($id, $this->workspace, $this->project, $this->task, preg_replace('/(\.[^.]+)$/', ' copy$1', $this->name, 1), $this->size, $by, $at);
    }

    /** @return array<string, mixed> the File in the frontend's shape (`files`) */
    public function toRecord(): array
    {
        return ['id' => $this->id, 'project' => $this->project->getId(), 'name' => $this->name, 'type' => $this->type, 'size' => $this->size, 'by' => $this->by, 'at' => $this->at, 'task' => $this->task?->getId()];
    }

    /** @return array<string, mixed> the File as its Task's Attachment (`attachments` of a Task) */
    public function toAttachment(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'size' => $this->size, 'by' => $this->by, 'at' => $this->at];
    }
}
