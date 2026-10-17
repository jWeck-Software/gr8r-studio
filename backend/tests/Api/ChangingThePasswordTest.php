<?php

namespace App\Tests\Api;

/** Sarah, signed in, changes her password in her settings. */
final class ChangingThePasswordTest extends ApiTestCase
{
    public function testSarahChangesHerPassword(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->change(self::DEMO_PASSWORD, 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $body);
        self::assertSame('m2', $this->getJson('/api/me')['member']);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');
        self::assertResponseStatusCodeSame(401);
        $this->signIn('sarah@gr8rstudio.com', 'sarahs-new-password-1');
        self::assertResponseStatusCodeSame(200);
    }

    public function testAWrongCurrentPasswordChangesNothing(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->change('not-her-password-1', 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'invalid_credentials', 'message' => 'Your current password is incorrect.'], $body);
        self::assertSame('m2', $this->getJson('/api/me')['member']);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com', 'sarahs-new-password-1');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAWeakNewPasswordIsRefused(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->change(self::DEMO_PASSWORD, 'short-1');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Use at least 10 characters, including a number or symbol.'], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');
        self::assertResponseStatusCodeSame(200);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function incompleteChanges(): iterable
    {
        yield 'no current password' => [['new' => 'sarahs-new-password-1'], 'Enter your current password.'];
        yield 'no new password' => [['current' => self::DEMO_PASSWORD], 'Choose a new password.'];
    }

    /** @param array<string, string> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteChanges')]
    public function testAnIncompleteChangeIsRefused(array $body, string $message): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        self::assertSame(['error' => 'invalid', 'message' => $message], $this->postJson('/api/me/password', $body));
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Changing it ends Sarah's other sessions, so whoever knew the old password is signed out;
     * the session she changed it in stays.
     */
    public function testSarahsOtherSessionsEnd(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $elsewhere = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->signIn('sarah@gr8rstudio.com');

        $this->change(self::DEMO_PASSWORD, 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(204);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(200);
        $this->client->getCookieJar()->clear();
        foreach ($elsewhere as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    /** A reset link Sarah asked for before stops working: whoever finds it in her inbox can't undo the change. */
    public function testAnOpenResetLinkStopsWorking(): void
    {
        $this->postJson('/api/password/forgot', ['email' => 'sarah@gr8rstudio.com']);
        $mail = self::getMailerMessage();
        \assert($mail instanceof \Symfony\Component\Mime\Email);
        preg_match('#\#reset/([A-Za-z0-9_-]{43})#', (string) $mail->getTextBody(), $reset);
        $this->signIn('sarah@gr8rstudio.com');

        $this->change(self::DEMO_PASSWORD, 'sarahs-new-password-1');

        $this->client->request('POST', '/api/logout');
        $this->postJson('/api/password/reset', ['token' => $reset[1], 'password' => 'someone-elses-1']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testChangingThePasswordNeedsASignedInUser(): void
    {
        $body = $this->change(self::DEMO_PASSWORD, 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthenticated', $body['error']);
    }

    /** @return array<mixed> */
    private function change(string $current, string $new): array
    {
        return $this->postJson('/api/me/password', ['current' => $current, 'new' => $new]);
    }
}
