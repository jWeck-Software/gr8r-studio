<?php

namespace App\Sync;

use App\Entity\Member;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Applies `put` and `delete` ops for one collection of S.data, such as `tasks`.
 *
 * Adding a collection to the sync means adding one class that implements this interface;
 * Sync picks it up by its type(). A handler validates the record, checks that the Member
 * may make the change, maps it onto entities and persists them, and derives whatever the
 * server owns (ADR 0003). It throws SyncRejected to refuse the batch; Sync flushes after
 * every op and runs the whole batch in one transaction.
 */
#[AutoconfigureTag]
interface CollectionHandler
{
    /** The collection's key in S.data, e.g. "tasks". */
    public function type(): string;

    /**
     * Creates or fully replaces the record with this id.
     *
     * @param array<string, mixed> $data the full record in the frontend's shape
     *
     * @throws SyncRejected
     */
    public function put(Member $actor, string $id, array $data): void;

    /**
     * Deletes the record with this id; an id the Member's Workspace doesn't have is a no-op.
     *
     * @throws SyncRejected
     */
    public function delete(Member $actor, string $id): void;
}
