<?php

namespace App\Sync;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of POST /api/sync, `{"ops": [...]}`: the envelope only. Sync checks each op, and each
 * handler checks its records with Fields, because records keep the frontend's shape (ADR 0002).
 */
final readonly class Batch
{
    private const string MALFORMED = 'The body must be {"ops": [...]}.';

    public function __construct(
        // The ops in the order they apply. Typed `mixed` so that the serializer takes any value and
        // the constraints refuse it with the message above.
        #[Assert\NotNull(message: self::MALFORMED)]
        #[Assert\Type('list', message: self::MALFORMED)]
        public mixed $ops = null,
    ) {
    }
}
