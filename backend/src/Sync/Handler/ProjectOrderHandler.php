<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Member;
use App\Entity\Project;
use App\Sync\SingletonHandler;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/** The Project order of the sidebar, shared by the whole Workspace. */
final readonly class ProjectOrderHandler implements SingletonHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
    ) {
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
        $visible = array_map(static fn (Project $p): string => $p->getId(), $this->access->visibleProjects($actor));

        $ws->setProjOrder(self::merge(
            array_values(array_unique(array_intersect($data, $visible))),
            array_values(array_intersect($ws->getProjOrder(), $existing)),
        ));
    }

    /**
     * The Member's new order, with every Project it leaves out (the ones the Member can't see,
     * above all) put back right after the Project it followed before.
     *
     * @param list<string> $sent
     * @param list<string> $stored
     *
     * @return list<string>
     */
    private static function merge(array $sent, array $stored): array
    {
        $order = $sent;
        foreach ($stored as $i => $id) {
            if (!\in_array($id, $order, true)) {
                $after = 0 === $i ? -1 : (int) array_search($stored[$i - 1], $order, true);
                array_splice($order, $after + 1, 0, [$id]);
            }
        }

        return $order;
    }
}
