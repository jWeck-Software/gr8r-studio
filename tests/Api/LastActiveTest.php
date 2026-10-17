<?php

namespace App\Tests\Api;

/**
 * A Member's `last`: the whole minutes since their last signed-in request, as the server saw it,
 * stored at most once a minute; null for an invited Member who never signed in.
 */
final class LastActiveTest extends ApiTestCase
{
    public function testASignedInMemberIsActiveNow(): void
    {
        $this->signIn('john@gr8rstudio.com');

        self::assertSame(0, $this->last('m3'));
    }

    public function testMembersWhoMakeNoRequestGetOlder(): void
    {
        $this->signIn();
        self::assertSame(4, $this->last('m2'));

        self::mockTime('+10 minutes');

        self::assertSame(14, $this->last('m2'));
        self::assertSame(0, $this->last('m1'));
    }

    public function testAnInvitedMemberWhoNeverSignedInHasNoLastActivity(): void
    {
        $this->signIn();

        self::assertNull($this->last('m8'));
    }

    public function testRequestsWithinAMinuteOfTheLastStoredOneAreNotStored(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $this->getJson('/api/bootstrap');
        self::mockTime('+50 seconds');
        $this->getJson('/api/bootstrap');
        $this->client->request('POST', '/api/logout');

        self::mockTime('+40 seconds');
        $this->signIn();

        self::assertSame(1, $this->last('m3'));
    }

    public function testSigningInCountsAsActivity(): void
    {
        $this->signIn('emma@gr8rstudio.com');
        $this->client->request('POST', '/api/logout');

        $this->signIn();

        self::assertSame(0, $this->last('m4'));
    }

    private function last(string $memberId): ?int
    {
        $members = array_column($this->getJson('/api/bootstrap')['data']['members'], null, 'id');
        self::assertResponseStatusCodeSame(200);

        return $members[$memberId]['last'];
    }
}
