<?php

namespace App\Tests\Api;

use Symfony\Component\Mime\Email;

/**
 * A visitor signs up, verifies their email address with the link they are sent, and starts their
 * own Workspace. Nina is someone new; Sarah has an account in the demo Workspace.
 */
final class SignUpTest extends ApiTestCase
{
    private const string LINK = '#^http://localhost:5173/\#verify/([A-Za-z0-9_-]{43})$#m';

    public function testSigningUpEmailsAVerificationLink(): void
    {
        $this->signUp();

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertSame('nina@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('Verify your email address for Gr8r Studio', $email->getSubject());
        self::assertMatchesRegularExpression(self::LINK, (string) $email->getTextBody());
    }

    /** Opening the link verifies Nina and signs her in, as a User without a Workspace yet. */
    public function testOpeningTheLinkVerifiesNinaAndSignsHerIn(): void
    {
        $this->signUp();

        $body = $this->verify($this->token());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'nina@example.test', 'name' => 'Nina Novak'], 'member' => null], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('no_workspace', $this->lastJson()['error']);
    }

    public function testTheLinkWorksOnce(): void
    {
        $this->signUp();
        $token = $this->token();
        $this->verify($token);
        $this->client->request('POST', '/api/logout');

        $body = $this->verify($token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_found', 'message' => 'This verification link is not valid any more. It may have been used already or replaced by a newer one.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testALinkAfter24HoursHasExpired(): void
    {
        $this->signUp();
        $token = $this->token();
        self::mockTime(new \DateTimeImmutable('2026-10-18 12:00:01', new \DateTimeZone('UTC')));

        $body = $this->verify($token);

        self::assertResponseStatusCodeSame(410);
        self::assertSame(['error' => 'expired', 'message' => 'This verification link has expired. Sign in to send yourself a new one.'], $body);
        $this->signIn('nina@example.test', 'nina-password-1');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnknownLinkVerifiesNobody(): void
    {
        $body = $this->verify(str_repeat('x', 43));

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $body['error']);
    }

    /** Nina asks for the email again: a new link, and the first one stops working. */
    public function testNinaGetsANewLink(): void
    {
        $this->signUp();
        $first = $this->token();

        $body = $this->postJson('/api/verify-email/resend', ['email' => 'Nina@example.test']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame([], $body);
        self::assertEmailCount(1);
        $second = $this->token();
        self::assertNotSame($first, $second);
        $this->verify($first);
        self::assertResponseStatusCodeSame(404);
        self::assertNull($this->verify($second)['member']);
    }

    /** @return iterable<string, array{string}> */
    public static function addressesWithNothingToVerify(): iterable
    {
        yield 'an address without an account' => ['nobody@example.test'];
        yield 'Sarah, who is verified' => ['sarah@gr8rstudio.com'];
    }

    /** The answer tells nothing about the address. */
    #[\PHPUnit\Framework\Attributes\DataProvider('addressesWithNothingToVerify')]
    public function testAskingForALinkForAnAddressWithNothingToVerifySendsNothing(string $email): void
    {
        $body = $this->postJson('/api/verify-email/resend', ['email' => $email]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame([], $body);
        self::assertEmailCount(0);
    }

    /** An Invite link proves the address too: Nina, invited before she verified, joins with her password and is verified. */
    public function testNinaInvitedBeforeSheVerifiedJoinsAndIsVerified(): void
    {
        $this->signUp();
        $this->signIn('sarah@gr8rstudio.com');
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [
            'id' => 'mq7x2', 'name' => 'Nina', 'email' => 'nina@example.test', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—',
        ]]]]);
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        preg_match('#\#join/([A-Za-z0-9_-]{43})#', (string) $email->getTextBody(), $match);
        $this->client->request('POST', '/api/logout');

        $body = $this->postJson('/api/invites/'.$match[1].'/accept', ['name' => 'Nina Novak', 'password' => 'nina-password-1']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('mq7x2', $body['member']);
        $this->client->request('POST', '/api/logout');
        self::assertSame('mq7x2', $this->signIn('nina@example.test', 'nina-password-1')['member']);
    }

    /** Before she opens the link, Nina's password is right but she is told to verify first. */
    public function testNinaCannotSignInBeforeSheVerifies(): void
    {
        $this->signUp();

        $body = $this->signIn('nina@example.test', 'nina-password-1');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'unverified', 'message' => 'Verify your email address first: open the link we emailed you, or send yourself a new one.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    /** Only someone who knows the password learns that the address waits for verification. */
    public function testAWrongPasswordBeforeVerifyingIsJustWrong(): void
    {
        $this->signUp();

        $body = $this->signIn('nina@example.test', 'not-her-password-1');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_credentials', $body['error']);
    }

    public function testOnceVerifiedNinaSignsInWithHerPassword(): void
    {
        $this->signUp();
        $this->verify($this->token());
        $this->client->request('POST', '/api/logout');

        $body = $this->signIn('nina@example.test', 'nina-password-1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'nina@example.test', 'name' => 'Nina Novak'], 'member' => null], $body);
    }

    /**
     * Signing up with Sarah's address answers like any other signup, so nobody learns it has an
     * account; Sarah is told by email instead, and her account stays as it was.
     */
    public function testSigningUpWithSarahsAddressTellsOnlySarah(): void
    {
        $body = $this->signUp('Sarah@gr8rstudio.com ', 'someone-elses-1', 'Not Sarah');

        self::assertResponseStatusCodeSame(202);
        self::assertSame([], $body);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertSame('sarah@gr8rstudio.com', $email->getTo()[0]->getAddress());
        self::assertSame('You already have a Gr8r Studio account', $email->getSubject());
        self::assertStringContainsString('http://localhost:5173/#login', (string) $email->getTextBody());
        self::assertDoesNotMatchRegularExpression(self::LINK, (string) $email->getTextBody());
        $this->signIn('sarah@gr8rstudio.com', 'someone-elses-1');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['user' => ['email' => 'sarah@gr8rstudio.com', 'name' => 'Sarah Chen'], 'member' => 'm2'], $this->signIn('sarah@gr8rstudio.com'));
    }

    /**
     * Signing up again before verifying starts over: the latest name and password count, and only
     * the newest link works. Whoever signed up with an address that is not theirs cannot keep it from its owner.
     */
    public function testSigningUpAgainBeforeVerifyingStartsOver(): void
    {
        $this->signUp(password: 'first-password-1');
        $first = $this->token();

        $this->signUp(password: 'second-password-2', name: 'Nina N.');

        self::assertResponseStatusCodeSame(202);
        $second = $this->token();
        self::assertNotSame($first, $second);
        $this->verify($first);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['user' => ['email' => 'nina@example.test', 'name' => 'Nina N.'], 'member' => null], $this->verify($second));
        $this->client->request('POST', '/api/logout');
        $this->signIn('nina@example.test', 'first-password-1');
        self::assertResponseStatusCodeSame(401);
        $this->signIn('nina@example.test', 'second-password-2');
        self::assertResponseStatusCodeSame(200);
    }

    /** @return iterable<string, array{string}> */
    public static function weakPasswords(): iterable
    {
        yield 'under 10 characters' => ['short-1'];
        yield 'letters only' => ['longpassword'];
        yield 'an underscore is no symbol' => ['under_score_x'];
    }

    /** As the app asks: at least 10 characters, including a number or symbol. */
    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswords')]
    public function testAWeakPasswordIsRefusedAndNobodyIsEmailed(string $password): void
    {
        $body = $this->signUp(password: $password);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Use at least 10 characters, including a number or symbol.'], $body);
        self::assertEmailCount(0);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function incompleteSignUps(): iterable
    {
        yield 'no name' => [' ', 'nina@example.test', 'Enter your name.'];
        yield 'no email address' => ['Nina Novak', '', 'Enter your email address.'];
        yield 'not an email address' => ['Nina Novak', 'nina-at-example', 'Enter a valid email address, like name@company.com.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteSignUps')]
    public function testAnIncompleteSignUpIsRefused(string $name, string $email, string $message): void
    {
        $body = $this->signUp($email, name: $name);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => $message], $body);
        self::assertEmailCount(0);
    }

    /** The token in the link of the last email sent; the mails of a request are gone after the next one. */
    private function token(): string
    {
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        preg_match(self::LINK, (string) $email->getTextBody(), $match);

        return $match[1] ?? self::fail('The last email has no verification link.');
    }

    /** @return array<mixed> */
    private function verify(string $token): array
    {
        return $this->postJson('/api/verify-email', ['token' => $token]);
    }

    /** @return array<mixed> */
    private function signUp(string $email = 'nina@example.test', string $password = 'nina-password-1', string $name = 'Nina Novak'): array
    {
        return $this->postJson('/api/signup', ['name' => $name, 'email' => $email, 'password' => $password]);
    }
}
