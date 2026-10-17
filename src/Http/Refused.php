<?php

namespace App\Http;

use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * What the server refuses, with its status, its error code and the reason to show. Thrown out of
 * a controller, JsonApiErrorListener answers it as {"error": "<code>", "message": "…"}, with codes
 * such as `expired` (410) that a status alone doesn't name. Logged as info: it is an answer, not a fault.
 */
#[WithLogLevel(LogLevel::INFO)]
abstract class Refused extends HttpException
{
    protected function __construct(
        int $status,
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($status, $message);
    }

    /** @return array{error: string, message: string} */
    public function body(): array
    {
        return ['error' => $this->error, 'message' => $this->getMessage()];
    }
}
