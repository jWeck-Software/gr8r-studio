<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** The shared space a team works in; it owns every Project, Task, Team and File. */
#[ORM\Entity]
class Workspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @param list<string> $projOrder */
    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 120, unique: true)]
        private string $url,
        #[ORM\Column(length: 20)]
        private string $c,
        #[ORM\Column]
        private bool $brand = false,
        #[ORM\Column(type: Types::JSON)]
        private array $projOrder = [],
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getC(): string
    {
        return $this->c;
    }

    public function isBrand(): bool
    {
        return $this->brand;
    }

    public function rename(string $name, string $url, string $c, bool $brand): void
    {
        $this->name = $name;
        $this->url = $url;
        $this->c = $c;
        $this->brand = $brand;
    }

    /** @return list<string> */
    public function getProjOrder(): array
    {
        return $this->projOrder;
    }

    /** @param list<string> $projOrder */
    public function setProjOrder(array $projOrder): void
    {
        $this->projOrder = $projOrder;
    }
}
