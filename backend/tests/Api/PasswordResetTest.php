<?php

namespace App\Tests\Api;

use Symfony\Component\Mime\Email;

/**
 * A User who forgot their password asks for a link by email and chooses a new password with it,
 * which signs them in. Sarah has an account in the demo Workspace; Nina signs up and never verifies.
 */
final class PasswordResetTest extends ApiTestCase
{
    private const string LINK = '#^http://localhost:5173/\#reset/([A-Za-z0-9_-]{43})$#m';

    public function testSarahIsEmailedAResetLink(): void
    {
        $body = $this->forgot('Sarah@gr8rstudio.com ');

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $body);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertSame('sarah@gr8rstudio.com', $email->getTo()[0]->getAddress());
        self::assertSame('Reset your Gr8r Studio password', $email->getSubject());
        self::assertMatchesRegularExpression(self::LINK, (string) $email->getTextBody());
    }

    /** The answer tells nothing about the address. */
    public function testAnAddressWithoutAnAccountIsEmailedNothing(): void
    {
        $body = $this->forgot('nobody@example.test');

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $body);
        self::assertEmailCount(0);
    }

    /** With the link, Sarah chooses a new password and is signed in; the old one stops working. */
    public function testSarahChoosesANewPasswordAndIsSignedIn(): void
    {
        $this->forgot('sarah@gr8rstudio.com');

        $body = $this->reset($this->token(), 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'sarah@gr8rstudio.com', 'name' => 'Sarah Chen'], 'member' => 'm2'], $body);
        self::assertSame($body, $this->getJson('/api/me'));
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');
        self::assertResponseStatusCodeSame(401);
        $this->signIn('sarah@gr8rstudio.com', 'sarahs-new-password-1');
        self::assertResponseStatusCodeSame(200);
    }

    public function testTheLinkWorksOnce(): void
    {
        $this->forgot('sarah@gr8rstudio.com');
        $token = $this->token();
        $this->reset($token, 'sarahs-new-password-1');
        $this->client->request('POST', '/api/logout');

        $body = $this->reset($token, 'sarahs-other-password-2');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_found', 'message' => 'This password reset link is not valid any more. It may have been used already or replaced by a newer one.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->signIn('sarah@gr8rstudio.com', 'sarahs-new-password-1');
        self::assertResponseStatusCodeSame(200);
    }

    public function testALinkAfter30MinutesHasExpired(): void
    {
        $this->forgot('sarah@gr8rstudio.com');
        $token = $this->token();
        self::mockTime(new \DateTimeImmutable('2026-10-17 12:30:01', new \DateTimeZone('UTC')));

        $body = $this->reset($token, 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(410);
        self::assertSame(['error' => 'expired', 'message' => 'This password reset link has expired. Ask for a new one from the sign-in page.'], $body);
        $this->signIn('sarah@gr8rstudio.com');
        self::assertResponseStatusCodeSame(200);
    }

    /** Asking again sends a new link; only the newest one works. */
    public function testOnlyTheNewestLinkWorks(): void
    {
        $this->forgot('sarah@gr8rstudio.com');
        $first = $this->token();
        $this->forgot('sarah@gr8rstudio.com');
        $second = $this->token();

        $this->reset($first, 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('m2', $this->reset($second, 'sarahs-new-password-1')['member']);
    }

    /** As the app asks: at least 10 characters, including a number or symbol; the link keeps working. */
    public function testAWeakPasswordIsRefusedAndTheLinkKeepsWorking(): void
    {
        $this->forgot('sarah@gr8rstudio.com');
        $token = $this->token();

        $body = $this->reset($token, 'longpassword');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Use at least 10 characters, including a number or symbol.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->reset($token, 'sarahs-new-password-1');
        self::assertResponseStatusCodeSame(200);
    }

    /** @return iterable<string, array{string, array<string, string>, string}> */
    public static function incompleteRequests(): iterable
    {
        yield 'forgot without an address' => ['/api/password/forgot', ['email' => ' '], 'Enter your email address.'];
        yield 'reset without a token' => ['/api/password/reset', ['password' => 'sarahs-new-password-1'], 'Send the token of the password reset link.'];
        yield 'reset without a password' => ['/api/password/reset', ['token' => 'abc'], 'Choose a new password.'];
    }

    /** @param array<string, string> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteRequests')]
    public function testAnIncompleteRequestIsRefused(string $path, array $body, string $message): void
    {
        self::assertSame(['error' => 'invalid', 'message' => $message], $this->postJson($path, $body));
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Nina signed up and lost the verification email: the reset link reached her address too, so
     * choosing a password verifies her and signs her in, as a User without a Workspace yet. The
     * verification link stops working.
     */
    public function testResettingVerifiesNina(): void
    {
        $this->postJson('/api/signup', ['name' => 'Nina Novak', 'email' => 'nina@example.test', 'password' => 'nina-password-1']);
        $mail = self::getMailerMessage();
        \assert($mail instanceof Email);
        preg_match('#\#verify/([A-Za-z0-9_-]{43})#', (string) $mail->getTextBody(), $verify);
        $this->forgot('nina@example.test');

        $body = $this->reset($this->token(), 'ninas-new-password-1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'nina@example.test', 'name' => 'Nina Novak'], 'member' => null], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('nina@example.test', 'ninas-new-password-1');
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/logout');
        $this->postJson('/api/verify-email', ['token' => $verify[1]]);
        self::assertResponseStatusCodeSame(404);
    }

    /** A new password ends Sarah's other sessions, such as one on a computer someone else may use. */
    public function testResettingSignsSarahOutEverywhereElse(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $elsewhere = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->forgot('sarah@gr8rstudio.com');

        $this->reset($this->token(), 'sarahs-new-password-1');

        self::assertResponseStatusCodeSame(200);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(200);
        $this->client->getCookieJar()->clear();
        foreach ($elsewhere as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    /** The token in the link of the last email sent. */
    private function token(): string
    {
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        preg_match(self::LINK, (string) $email->getTextBody(), $match);

        return $match[1] ?? self::fail('The last email has no reset link.');
    }

    /** @return array<mixed> */
    private function reset(string $token, string $password): array
    {
        return $this->postJson('/api/password/reset', ['token' => $token, 'password' => $password]);
    }

    /** @return array<mixed> */
    private function forgot(string $email): array
    {
        return $this->postJson('/api/password/forgot', ['email' => $email]);
    }
}
