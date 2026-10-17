<?php

namespace App\Sync;

use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Looks up a record by the id the client generated (ADR 0003). Ids are unique across Workspaces,
 * so an id that another Workspace uses refuses the batch, and handlers only ever get records of
 * the Member's own Workspace.
 */
final readonly class WorkspaceRecords
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class an entity with getWorkspace()
     *
     * @return T|null the record of the Member's Workspace with this id; null when no Workspace uses it
     *
     * @throws SyncRejected when another Workspace uses the id
     */
    public function find(string $class, Member $actor, string $id): ?object
    {
        $record = $this->em->find($class, $id);
        if (null !== $record && $record->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The id "%s" is already used.', $id));
        }

        return $record;
    }
}
