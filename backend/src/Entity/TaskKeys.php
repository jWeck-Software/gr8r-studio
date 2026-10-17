<?php

namespace App\Entity;

/**
 * How Task keys are numbered within a Project. Keys are client-derived (ADR 0003): the
 * frontend numbers a new Task one after its Project's `seq`, which the snapshot reports, and
 * the server numbers a new Task the same way when the key it brought is already taken.
 */
final class TaskKeys
{
    /** The frontend numbers a Project's first new Task 201, after the seeded keys. */
    public const int FIRST_SEQ = 200;

    /**
     * The highest number among a Project's Task keys, archived Tasks included, and at least FIRST_SEQ.
     *
     * @param iterable<string> $keys
     */
    public static function seq(iterable $keys): int
    {
        $seq = self::FIRST_SEQ;
        foreach ($keys as $key) {
            $seq = max($seq, (int) substr($key, strrpos($key, '-') + 1));
        }

        return $seq;
    }

    /** @param iterable<string> $keys the keys in use in the Project */
    public static function next(string $projectKey, iterable $keys): string
    {
        return $projectKey.'-'.(self::seq($keys) + 1);
    }
}
