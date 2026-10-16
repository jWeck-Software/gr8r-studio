<?php

namespace App\Tests\Api;

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
}
