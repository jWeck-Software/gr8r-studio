<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A named group of Members inside a Workspace, such as Design or Engineering. */
#[ORM\Entity]
class Team
{
    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 60)]
    private string $icon = '';

    #[ORM\Column(length: 20)]
    private string $c = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

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

    /** @param array{name: string, icon: string, c: string, desc: string} $record a valid Team in the frontend's shape, without its id */
    public function update(array $record): void
    {
        $this->name = $record['name'];
        $this->icon = $record['icon'];
        $this->c = $record['c'];
        $this->description = $record['desc'];
    }

    /** @return array{id: string, name: string, icon: string, c: string, desc: string} the Team in the frontend's shape */
    public function toRecord(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'icon' => $this->icon, 'c' => $this->c, 'desc' => $this->description];
    }
}
