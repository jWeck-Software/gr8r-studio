<?php

namespace App\File;

/** What the server refuses about a File, with the reason to show. */
final class FileRefused extends \RuntimeException
{
    private function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** The upload or its fields break the contract: 422. */
    public static function invalid(string $message): self
    {
        return new self(422, 'invalid', $message);
    }

    /** The Member may not add or change Files of this Project: 403. */
    public static function forbidden(string $message): self
    {
        return new self(403, 'forbidden', $message);
    }

    /** No File with this id that the Member sees: 404, the same for one in a Private project they are not in. */
    public static function notFound(): self
    {
        return new self(404, 'not_found', 'There is no such File, or it was deleted.');
    }

    /** @return array{error: string, message: string} */
    public function body(): array
    {
        return ['error' => $this->error, 'message' => $this->getMessage()];
    }
}
