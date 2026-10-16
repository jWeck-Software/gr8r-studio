<?php

namespace App\Sync;

/**
 * A batch the server refuses as a whole. Handlers throw it without an op index;
 * Sync adds the index of the op that failed before it reaches the caller.
 */
final class SyncRejected extends \RuntimeException
{
    private function __construct(
        public readonly string $error,
        string $message,
        public readonly ?int $op = null,
    ) {
        parent::__construct($message);
    }

    /** The op or its record breaks the contract: 422. */
    public static function invalid(string $message): self
    {
        return new self('invalid', $message);
    }

    /** The Member may not make this change: 403. */
    public static function forbidden(string $message): self
    {
        return new self('forbidden', $message);
    }

    public function atOp(int $op): self
    {
        return new self($this->error, $this->getMessage(), $op);
    }

    public function status(): int
    {
        return 'forbidden' === $this->error ? 403 : 422;
    }

    /** @return array{error: string, op?: int, message: string} */
    public function body(): array
    {
        return ['error' => $this->error, ...(null === $this->op ? [] : ['op' => $this->op]), 'message' => $this->getMessage()];
    }
}
