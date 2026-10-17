<?php

namespace App\Tests\Api;

final class SignInTest extends ApiTestCase
{
    public function testDemoOwnerSignsInWithEmailAndPassword(): void
    {
        $body = $this->signIn('hello@gr8rstudio.com', 'gr8r-studio-demo');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'hello@gr8rstudio.com', 'name' => 'Tanjim Islam'], 'member' => 'm1'], $body);
    }

    public function testEveryActiveDemoMemberCanSignIn(): void
    {
        $body = $this->signIn('lena@gr8rstudio.com', 'gr8r-studio-demo');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'lena@gr8rstudio.com', 'name' => 'Lena Fischer'], 'member' => 'm7'], $body);
    }

    public function testWrongPasswordIsRefusedWithAClearMessage(): void
    {
        $body = $this->signIn('hello@gr8rstudio.com', 'not-the-password');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'invalid_credentials', 'message' => 'Email or password is incorrect.'], $body);
    }

    public function testUnknownEmailIsRefusedLikeAWrongPassword(): void
    {
        $body = $this->signIn('nobody@gr8rstudio.com', 'gr8r-studio-demo');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_credentials', $body['error']);
    }

    public function testLoginWithoutJsonBodyIsABadRequest(): void
    {
        $this->client->request('POST', '/api/login', ['email' => 'hello@gr8rstudio.com', 'password' => 'gr8r-studio-demo']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid', $this->lastJson()['error']);
    }

    public function testSignedInUserStaysSignedInAcrossRequests(): void
    {
        $this->signIn();

        $body = $this->getJson('/api/me');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'hello@gr8rstudio.com', 'name' => 'Tanjim Islam'], 'member' => 'm1'], $body);
    }

    public function testMeWithoutSessionIsUnauthenticated(): void
    {
        $body = $this->getJson('/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'unauthenticated', 'message' => 'Sign in to continue.'], $body);
    }

    public function testSignOutEndsTheSession(): void
    {
        $this->signIn();

        $this->client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testSignOutWithoutSessionIsUnauthenticated(): void
    {
        $body = $this->postJson('/api/logout', []);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthenticated', $body['error']);
    }
}
