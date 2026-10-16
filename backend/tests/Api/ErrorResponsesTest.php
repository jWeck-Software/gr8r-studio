<?php

namespace App\Tests\Api;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ErrorResponsesTest extends ApiTestCase
{
    public function testUnknownEndpointAnswersWithAJsonError(): void
    {
        $body = $this->getJson('/api/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $body['error']);
    }

    public function testWrongMethodAnswersWithAJsonError(): void
    {
        $body = $this->getJson('/api/sync');

        self::assertResponseStatusCodeSame(405);
        self::assertSame('method_not_allowed', $body['error']);
    }

    public function testUnexpectedFailureAnswersWithAJsonErrorThatKeepsTheDetailsInside(): void
    {
        static::getContainer()->get('event_dispatcher')->addListener(KernelEvents::REQUEST, static function (RequestEvent $event): void {
            throw new \RuntimeException('SQLSTATE[08006] connection to 10.0.0.5 failed');
        });

        $body = $this->getJson('/api/health');

        self::assertResponseStatusCodeSame(500);
        self::assertSame(['error' => 'server_error', 'message' => 'Something went wrong on the server. Please try again.'], $body);
    }
}
