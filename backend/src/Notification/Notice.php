<?php

namespace App\Notification;

/**
 * A Notification the rules decided on, before it is stored: who gets it, what kind it is,
 * who caused it, which Task it is about and the two lines the frontend shows.
 */
final readonly class Notice
{
    public function __construct(
        public string $recipient,
        /** mention, assign or comment */
        public string $type,
        public string $by,
        public string $task,
        public string $text,
        public string $snippet,
    ) {
    }
}
