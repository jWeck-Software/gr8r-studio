<?php

namespace App\Tests\Api;

/**
 * Members as the frontend changes them: Roles and Teams by Owners and Admins, their own name,
 * title and avatar colour by everyone. Sarah is an Admin, John and Emma are Members.
 */
final class MembersTest extends ApiTestCase
{
    public function testTheOwnerMovesSarahToAnotherTeam(): void
    {
        $this->signIn();

        $body = $this->putMember('m2', ['team' => 'product']);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('sarah@gr8rstudio.com');
        self::assertSame('product', $this->member('m2')['team']);
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function changesForAdmins(): iterable
    {
        yield 'his own Role' => ['m3', ['role' => 'Admin'], 'Only Owners and Admins change Roles.'];
        yield 'his own Team' => ['m3', ['team' => 'product'], 'Only Owners and Admins assign Teams.'];
        yield "Emma's Role" => ['m4', ['role' => 'Guest'], 'Only Owners and Admins change Roles.'];
        yield "Emma's Team" => ['m4', ['team' => 'eng'], 'Only Owners and Admins assign Teams.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('changesForAdmins')]
    public function testJohnAMemberChangesNoRolesOrTeams(string $id, array $changes, string $message): void
    {
        $this->signIn('john@gr8rstudio.com');
        $before = $this->member($id);

        $body = $this->putMember($id, $changes);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
        self::assertSame($before, $this->member($id));
    }

    public function testJohnEditsHisOwnNameTitleAndAvatarColour(): void
    {
        $this->signIn('john@gr8rstudio.com');

        $body = $this->putMember('m3', ['name' => 'Johnny Carter', 'title' => 'Staff Engineer', 'c' => '#23918A']);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('sarah@gr8rstudio.com');
        $john = $this->member('m3');
        self::assertSame(['Johnny Carter', 'Staff Engineer', '#23918A'], [$john['name'], $john['title'], $john['c']]);
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function someoneElsesProfile(): iterable
    {
        yield "John, Emma's name" => ['john@gr8rstudio.com', 'm4', ['name' => 'Em Wilson']];
        yield "Sarah the Admin, John's title" => ['sarah@gr8rstudio.com', 'm3', ['title' => 'Intern']];
        yield "the Owner, John's avatar colour" => [self::OWNER_EMAIL, 'm3', ['c' => '#C54B78']];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('someoneElsesProfile')]
    public function testNobodyEditsSomeoneElsesProfile(string $email, string $id, array $changes): void
    {
        $this->signIn($email);

        $body = $this->putMember($id, $changes);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'Only the Member themselves changes their name, title and avatar colour.'], $body);
    }

    public function testTheOwnerMakesJohnAnAdmin(): void
    {
        $this->signIn();

        $body = $this->putMember('m3', ['role' => 'Admin']);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame('Admin', $this->member('m3')['role']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function ownerChangesByAnAdmin(): iterable
    {
        yield 'make John an Owner' => ['m3', 'Owner'];
        yield 'make the Owner an Admin' => ['m1', 'Admin'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ownerChangesByAnAdmin')]
    public function testOnlyAnOwnerDecidesWhoIsAnOwner(string $id, string $role): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->putMember($id, ['role' => $role]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'Only an Owner changes an Owner\'s Role or makes someone an Owner.'], $body);
    }

    public function testTheOnlyOwnerCannotStepDown(): void
    {
        $this->signIn();

        $body = $this->putMember('m1', ['role' => 'Admin']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'The Workspace needs at least one Owner.'], $body);
    }

    public function testAnOwnerHandsTheWorkspaceToSarahAndStepsDown(): void
    {
        $this->signIn();

        $body = $this->sync([
            ['op' => 'put', 'type' => 'members', 'id' => 'm1', 'data' => [...$this->member('m1'), 'role' => 'Admin']],
            ['op' => 'put', 'type' => 'members', 'id' => 'm2', 'data' => [...$this->member('m2'), 'role' => 'Owner']],
        ]);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'The Workspace needs at least one Owner.'], $body, 'stepping down first leaves no Owner');

        $body = $this->sync([
            ['op' => 'put', 'type' => 'members', 'id' => 'm2', 'data' => [...$this->member('m2'), 'role' => 'Owner']],
            ['op' => 'put', 'type' => 'members', 'id' => 'm1', 'data' => [...$this->member('m1'), 'role' => 'Admin']],
        ]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('sarah@gr8rstudio.com');
        self::assertSame(['Admin', 'Owner'], [$this->member('m1')['role'], $this->member('m2')['role']]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidMembers(): iterable
    {
        yield 'empty name' => [['name' => ' '], 'The Member\'s "name" must be a non-empty string.'];
        yield 'overlong title' => [['title' => str_repeat('x', 121)], 'The Member\'s "title" must be a string of at most 120 characters.'];
        yield 'colour by name' => [['c' => 'teal'], 'The Member\'s "c" must be a colour like #5A67D8.'];
        yield 'unknown Role' => [['role' => 'Superuser'], 'The Member\'s "role" must be one of Owner, Admin, Member, Guest.'];
        yield 'unknown Team' => [['team' => 'sales'], 'The Member\'s team "sales" is not a Team of this Workspace.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMembers')]
    public function testInvalidMembersAreRefused(array $changes, string $message): void
    {
        $this->signIn();

        $body = $this->putMember('m1', $changes);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    /** Email, status, last active and time zone are the server's; a put that sends others keeps them. */
    public function testWhatTheServerKeepsOfAMemberIsNotChangedByAPut(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $before = $this->member('m3');

        $body = $this->putMember('m3', ['email' => 'boss@gr8rstudio.com', 'status' => 'invited', 'last' => 0, 'tz' => 'Paris']);

        self::assertSame(['ok' => true], $body);
        self::assertSame($before, $this->member('m3'));
    }

    /** As the invite dialog adds them: invited, with a name made from the address, never signed in. */
    public function testSarahInvitesSomeoneAsAnInvitedMember(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => self::invited()]]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame(self::invited(), $this->member('mq7x2'));
        $this->client->request('POST', '/api/logout');
        $this->signIn('ana@gr8rstudio.com');
        self::assertResponseStatusCodeSame(401);
    }

    /** The share dialog invites a Guest by email and adds them to the Project in the same save. */
    public function testSarahSharesAProjectWithSomeoneNew(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $p1 = array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')['p1'];

        $body = $this->sync([
            ['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => self::invited(['role' => 'Guest', 'title' => 'Guest'])],
            ['op' => 'put', 'type' => 'projects', 'id' => 'p1', 'data' => [...$p1, 'members' => [...$p1['members'], 'mq7x2']]],
        ]);

        self::assertSame(['ok' => true], $body);
        self::assertContains('mq7x2', array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')['p1']['members']);
    }

    /** @return iterable<string, array{string, array<string, mixed>, int, string}> */
    public static function refusedInvites(): iterable
    {
        yield 'by John, a Member' => ['john@gr8rstudio.com', [], 403, 'Only Owners and Admins invite people.'];
        yield 'as an Owner by Sarah, an Admin' => ['sarah@gr8rstudio.com', ['role' => 'Owner'], 403, 'Only an Owner changes an Owner\'s Role or makes someone an Owner.'];
        yield 'already active' => ['sarah@gr8rstudio.com', ['status' => 'active'], 422, 'A new Member is invited: their "status" must be "invited".'];
        yield 'without a valid email' => ['sarah@gr8rstudio.com', ['email' => 'ana'], 422, 'The Member\'s "email" must be an email address.'];
        yield "with Emma's email" => ['sarah@gr8rstudio.com', ['email' => 'Emma@gr8rstudio.com'], 422, 'emma@gr8rstudio.com is already a Member of this Workspace.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedInvites')]
    public function testInvitesAreRefused(string $email, array $changes, int $status, string $message): void
    {
        $this->signIn($email);

        $body = $this->sync([['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => self::invited($changes)]]);

        self::assertResponseStatusCodeSame($status);
        self::assertSame(['error' => 403 === $status ? 'forbidden' : 'invalid', 'op' => 0, 'message' => $message], $body);
        self::assertNull($this->member('mq7x2'));
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function invited(array $changes = []): array
    {
        return [
            'id' => 'mq7x2', 'name' => 'Ana', 'email' => 'ana@gr8rstudio.com', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—', ...$changes,
        ];
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<mixed>
     */
    private function putMember(string $id, array $changes): array
    {
        return $this->sync([['op' => 'put', 'type' => 'members', 'id' => $id, 'data' => [...$this->member($id), ...$changes]]]);
    }

    /**
     * @param list<array<string, mixed>> $ops
     *
     * @return array<mixed>
     */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the Member as /api/bootstrap shows it */
    private function member(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['members'], null, 'id')[$id] ?? null;
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }
}
