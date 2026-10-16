<?php

namespace App\Sync;

use App\Entity\Member;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Applies `set` ops for one singleton of S.data, such as `projOrder`: a value that exists
 * once per Workspace or Member and is replaced as a whole.
 *
 * Adding a singleton to the sync means adding one class that implements this interface;
 * Sync picks it up by its type(), as it does for CollectionHandler.
 */
#[AutoconfigureTag]
interface SingletonHandler
{
    /** The singleton's key in S.data, e.g. "projOrder". */
    public function type(): string;

    /**
     * Replaces the value.
     *
     * @param mixed $data the whole value in the frontend's shape
     *
     * @throws SyncRejected
     */
    public function set(Member $actor, mixed $data): void;
}
