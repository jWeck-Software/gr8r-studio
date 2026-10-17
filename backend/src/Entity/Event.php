<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A dated meeting on a Project's calendar, seen by whoever sees the Project. */
#[ORM\Entity]
class Event
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 200)]
    private string $title = '';

    /** YYYY-MM-DD */
    #[ORM\Column(length: 10)]
    private string $date = '';

    /** HH:MM, 24-hour */
    #[ORM\Column(length: 5)]
    private string $time = '';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 44)]
        private string $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,
        Project $project,
    ) {
        $this->project = $project;
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

    /** @param array{title: string, date: string, time: string} $record a valid Event in the frontend's shape, without its id and Project */
    public function update(array $record, Project $project): void
    {
        $this->project = $project;
        $this->title = $record['title'];
        $this->date = $record['date'];
        $this->time = $record['time'];
    }

    /** @return array{id: string, title: string, date: string, time: string, project: string} the Event in the frontend's shape */
    public function toRecord(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'date' => $this->date, 'time' => $this->time, 'project' => $this->project->getId()];
    }
}
