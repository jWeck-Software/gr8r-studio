<?php

namespace App\Tests\Api;

use App\Demo\DemoSeed;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Talks to the backend over HTTP only. Every test starts from the demo Workspace,
 * seeded "today" on 2026-10-17 at noon, inside a transaction that is rolled back afterwards, and
 * with no rate limit used up.
 */
abstract class ApiTestCase extends WebTestCase
{
    use ClockSensitiveTrait;

    protected const string DEMO_PASSWORD = 'gr8r-studio-demo';
    protected const string OWNER_EMAIL = 'hello@gr8rstudio.com';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-17 12:00:00', new \DateTimeZone('UTC')));
        $this->client = static::createClient();
        // The rate limits (App\Security\RateLimits) count in a cache that outlives the test's transaction.
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->get(DemoSeed::class)->load();
    }

    /** @return array<mixed> */
    protected function signIn(string $email = self::OWNER_EMAIL, string $password = self::DEMO_PASSWORD): array
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    /** @return array<mixed> */
    protected function getJson(string $path): array
    {
        $this->client->request('GET', $path);

        return $this->lastJson();
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    protected function postJson(string $path, array $body): array
    {
        $this->client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->lastJson();
    }

    /**
     * Strict comparison of JSON records that ignores the order of object keys:
     * PostgreSQL's JSONB does not keep it, and the frontend does not depend on it.
     *
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    protected static function assertRecord(array $expected, array $actual, string $message = ''): void
    {
        self::assertSame(self::sortKeys($expected), self::sortKeys($actual), $message);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        $value = array_map(self::sortKeys(...), $value);
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @return array<mixed> */
    protected function lastJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
