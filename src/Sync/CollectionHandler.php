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
 * server owns (ADR 0003). It looks the record up through WorkspaceRecords, which refuses ids
 * of other Workspaces. It throws SyncRejected to refuse the batch; Sync flushes after every op
 * and runs the whole batch in one transaction.
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
     * @return array<string, array<string, mixed>> what the client has to learn because the server
     *                                             stored something other than it sent, merged into
     *                                             the response, e.g. `['keys' => ['t9' => 'WEB-207']]`;
     *                                             usually empty
     *
     * @throws SyncRejected
     */
    public function put(Member $actor, string $id, array $data): array;

    /**
     * Deletes the record with this id; an id the Member's Workspace doesn't have is a no-op.
     *
     * @throws SyncRejected
     */
    public function delete(Member $actor, string $id): void;
}
