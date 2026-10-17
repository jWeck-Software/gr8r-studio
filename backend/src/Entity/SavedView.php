<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A named set of filters on one Project, opened as a tab of the Project by whoever sees it.
 * Each filter is value-like and stored with it: `{f, op, v[]}` as the frontend's view engine has them.
 */
#[ORM\Entity]
class SavedView
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 120)]
    private string $name = '';

    /** The layout it opens in: list, board, table, … */
    #[ORM\Column(length: 20)]
    private string $type = '';

    /** @var list<array{f: string, op: string, v: list<string>}> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $filters = [];

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

    /** @param array{name: string, type: string, filters: list<array{f: string, op: string, v: list<string>}>} $record a valid Saved view in the frontend's shape, without its id and Project */
    public function update(array $record, Project $project): void
    {
        $this->project = $project;
        $this->name = $record['name'];
        $this->type = $record['type'];
        $this->filters = $record['filters'];
    }

    /** @return array<string, mixed> the Saved view in the frontend's shape (`savedViews`) */
    public function toRecord(): array
    {
        return ['id' => $this->id, 'project' => $this->project->getId(), 'name' => $this->name, 'type' => $this->type, 'filters' => $this->filters];
    }
}
