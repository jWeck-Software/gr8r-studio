<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * HTTP errors under /api answer as {"error": "<code>", "message": "…"} instead of an HTML page.
 * Sign-in problems are answered earlier by the firewall; unexpected errors keep Symfony's handling.
 */
#[AsEventListener]
final class JsonApiErrorListener
{
    private const array CODES = [
        400 => 'bad_request',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        415 => 'unsupported_media_type',
        422 => 'invalid',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $status = $exception->getStatusCode();
        $event->setResponse(new JsonResponse(
            ['error' => self::CODES[$status] ?? 'error', 'message' => $exception->getMessage()],
            $status,
            $exception->getHeaders(),
        ));
    }
}
