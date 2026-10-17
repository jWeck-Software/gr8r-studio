<?php

namespace App\Tests\Api;

/**
 * Guessing passwords and sending emails to someone else's address are slowed down: past a limit
 * per minute or quarter hour the answer is 429 `too_many_requests`, with Retry-After in seconds.
 * The limits are the same in the tests as in production (config/packages/rate_limiter.yaml);
 * ApiTestCase starts every test with none used up.
 */
final class RateLimitsTest extends ApiTestCase
{
    /** Five wrong passwords for Sarah's address are refused as wrong; the sixth attempt is not even checked. */
    public function testTheSixthSignInWithinAMinuteIsRefused(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->signIn('sarah@gr8rstudio.com', 'guess-'.$i);
            self::assertResponseStatusCodeSame(401);
        }

        $body = $this->signIn('sarah@gr8rstudio.com');

        self::assertResponseStatusCodeSame(429);
        self::assertSame('too_many_requests', $body['error']);
        self::assertMatchesRegularExpression('/^Too many attempts\. Try again in \d+ seconds\.$/', $body['message']);
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        // The minute counted from the first attempt, of which hardly a second has passed.
        self::assertGreaterThanOrEqual(55, $retryAfter);
        self::assertLessThanOrEqual(60, $retryAfter);
    }

    /** Only wrong passwords add up: signing in starts the count over. */
    public function testSigningInStartsTheCountOver(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->signIn('sarah@gr8rstudio.com', 'guess-'.$i);
        }
        $this->signIn('sarah@gr8rstudio.com');
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/logout');

        for ($i = 0; $i < 5; ++$i) {
            $this->signIn('sarah@gr8rstudio.com', 'guess-'.$i);
            self::assertResponseStatusCodeSame(401);
        }
    }

    /** The count is per address: someone else signing in from the same place is not held up. */
    public function testOtherAddressesStillSignIn(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->signIn('Sarah@gr8rstudio.com ', 'guess-'.$i);
        }
        self::assertResponseStatusCodeSame(429);

        self::assertSame('m1', $this->signIn()['member']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function emailsToOneAddress(): iterable
    {
        yield 'signing up' => ['/api/signup', 'nina@example.test'];
        yield 'a new verification link' => ['/api/verify-email/resend', 'nina@example.test'];
        yield 'a password reset link' => ['/api/password/forgot', 'sarah@gr8rstudio.com'];
    }

    /** Three emails to one address within a quarter hour; the fourth request sends none. */
    #[\PHPUnit\Framework\Attributes\DataProvider('emailsToOneAddress')]
    public function testTheFourthEmailToOneAddressIsNotSent(string $path, string $email): void
    {
        $this->postJson('/api/signup', ['name' => 'Nina Novak', 'email' => 'nina@example.test', 'password' => 'nina-password-1']);
        for ($i = '/api/signup' === $path ? 1 : 0; $i < 3; ++$i) {
            $this->postJson($path, ['name' => 'Nina Novak', 'email' => $email, 'password' => 'nina-password-1']);
            self::assertResponseIsSuccessful();
        }

        $body = $this->postJson($path, ['name' => 'Nina Novak', 'email' => strtoupper($email), 'password' => 'nina-password-1']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('too_many_requests', $body['error']);
        self::assertEmailCount(0);
        self::assertMatchesRegularExpression('/^Too many attempts\. Try again in 15 minutes\.$/', $body['message']);
    }

    /** An Owner or Admin sends one invited Member's Invite again at most three times a quarter hour. */
    public function testTheFourthResendOfAnInviteIsNotSent(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [
            'id' => 'mq7x2', 'name' => 'Nina', 'email' => 'nina@example.test', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—',
        ]]]]);
        for ($i = 0; $i < 3; ++$i) {
            $this->client->request('POST', '/api/members/mq7x2/invite');
            self::assertResponseStatusCodeSame(204);
        }

        $this->client->request('POST', '/api/members/mq7x2/invite');

        self::assertResponseStatusCodeSame(429);
        self::assertEmailCount(0);
    }

    /** Only a signed-in Owner's or Admin's resends count: someone without a session can't use them up. */
    public function testResendsWithoutASessionDoNotCount(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [
            'id' => 'mq7x2', 'name' => 'Nina', 'email' => 'nina@example.test', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—',
        ]]]]);
        $owner = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        for ($i = 0; $i < 3; ++$i) {
            $this->client->request('POST', '/api/members/mq7x2/invite');
            self::assertResponseStatusCodeSame(401);
        }
        foreach ($owner as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }

        $this->client->request('POST', '/api/members/mq7x2/invite');

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
    }

    /** Accepting one Invite link checks an existing account's password: five tries a minute. */
    public function testTheSixthAcceptOfOneInviteLinkIsRefused(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [
            'id' => 'mq7x2', 'name' => 'Nina', 'email' => 'nina@example.test', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—',
        ]]]]);
        $mail = self::getMailerMessage();
        \assert($mail instanceof \Symfony\Component\Mime\Email);
        preg_match('#\#join/([A-Za-z0-9_-]{43})#', (string) $mail->getTextBody(), $join);
        $this->client->request('POST', '/api/logout');
        for ($i = 0; $i < 5; ++$i) {
            $this->postJson('/api/invites/'.$join[1].'/accept', ['name' => 'Nina', 'password' => 'short']);
            self::assertResponseStatusCodeSame(422);
        }

        $this->postJson('/api/invites/'.$join[1].'/accept', ['name' => 'Nina', 'password' => 'nina-password-1']);

        self::assertResponseStatusCodeSame(429);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    /** @return iterable<string, array{string, \Closure(int): array<string, string>}> */
    public static function endpointsPerIp(): iterable
    {
        yield 'signing in' => ['/api/login', static fn (int $i) => ['email' => "someone-$i@example.test", 'password' => 'guess-1']];
        yield 'signing up' => ['/api/signup', static fn (int $i) => ['name' => 'Someone', 'email' => "someone-$i@example.test", 'password' => 'someone-password-1']];
        yield 'verifying' => ['/api/verify-email', static fn (int $i) => ['token' => str_repeat((string) ($i % 10), 43)]];
        yield 'a new verification link' => ['/api/verify-email/resend', static fn (int $i) => ['email' => "someone-$i@example.test"]];
        yield 'a password reset link' => ['/api/password/forgot', static fn (int $i) => ['email' => "someone-$i@example.test"]];
        yield 'a new password' => ['/api/password/reset', static fn (int $i) => ['token' => str_repeat((string) ($i % 10), 43), 'password' => 'someone-password-1']];
        yield 'accepting Invites' => ['/api/invites/link-%d/accept', static fn (int $i) => ['name' => 'Someone', 'password' => 'someone-password-1']];
    }

    /**
     * From one IP, each of these endpoints answers 20 requests a minute, whatever addresses or
     * links they name.
     *
     * @param \Closure(int): array<string, string> $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('endpointsPerIp')]
    public function testThe21stRequestFromOneIpWithinAMinuteIsRefused(string $path, \Closure $body): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $this->postJson(\sprintf($path, $i), $body($i));
            self::assertNotSame(429, $this->client->getResponse()->getStatusCode(), (string) $i);
        }

        $this->postJson(\sprintf($path, 20), $body(20));

        self::assertResponseStatusCodeSame(429);
    }

    /** Changing the password checks the current one: 20 tries a minute from one IP. */
    public function testThe21stPasswordChangeWithinAMinuteIsRefused(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        for ($i = 0; $i < 20; ++$i) {
            $this->postJson('/api/me/password', ['current' => 'guess-'.$i, 'new' => 'sarahs-new-password-1']);
            self::assertResponseStatusCodeSame(401);
        }

        $this->postJson('/api/me/password', ['current' => self::DEMO_PASSWORD, 'new' => 'sarahs-new-password-1']);

        self::assertResponseStatusCodeSame(429);
        $this->client->request('POST', '/api/logout');
        self::assertSame('m2', $this->signIn('sarah@gr8rstudio.com')['member']);
    }
}
