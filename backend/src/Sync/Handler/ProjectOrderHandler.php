<?php

namespace App\Sync\Handler;

use App\Entity\Member;
use App\Entity\Project;
use App\Sync\SingletonHandler;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/** The Project order of the sidebar, shared by the whole Workspace. */
final readonly class ProjectOrderHandler implements SingletonHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function type(): string
    {
        return 'projOrder';
    }

    public function set(Member $actor, mixed $data): void
    {
        if (!\is_array($data) || !array_is_list($data) || [] !== array_filter($data, static fn (mixed $id): bool => !\is_string($id))) {
            throw SyncRejected::invalid('The Project order must be a list of Project ids.');
        }
        $ws = $actor->getWorkspace();
        $existing = array_column($this->em->createQuery('SELECT p.id FROM '.Project::class.' p WHERE p.workspace = :ws')->setParameter('ws', $ws)->getScalarResult(), 'id');

        $ws->setProjOrder(array_values(array_unique(array_intersect($data, $existing))));
    }
}
