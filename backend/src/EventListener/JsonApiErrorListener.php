<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Errors under /api answer as {"error": "<code>", "message": "…"} instead of an HTML page. A request
 * body that #[MapRequestPayload] can't use is 422 `invalid`: one that breaks the payload's
 * constraints with their message, one that isn't JSON with Symfony's.
 * Sign-in problems are answered earlier by the firewall. Unexpected errors become a 500
 * `server_error` whose message names the cause only in the dev environment.
 *
 * Runs after Symfony logs the exception (priority 0) and before its HTML error page (-128).
 */
#[AsEventListener(priority: -64)]
final readonly class JsonApiErrorListener
{
    private const array CODES = [
        400 => 'bad_request',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        415 => 'unsupported_media_type',
        422 => 'invalid',
    ];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            $message = 'dev' === $this->environment ? $exception->getMessage() : 'Something went wrong on the server. Please try again.';
            $event->setResponse(new JsonResponse(['error' => 'server_error', 'message' => $message], 500));

            return;
        }
        $status = $exception->getStatusCode();
        if ($exception instanceof BadRequestHttpException && $exception->getPrevious() instanceof NotEncodableValueException) {
            // A #[MapRequestPayload] body that isn't JSON is refused like one of the wrong shape.
            $status = 422;
        }
        $event->setResponse(new JsonResponse(
            ['error' => self::CODES[$status] ?? 'error', 'message' => $exception->getMessage()],
            $status,
            $exception->getHeaders(),
        ));
    }
}
