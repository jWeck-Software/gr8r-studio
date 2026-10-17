<?php

namespace App\Tests\Api;

/** Owners and Admins create, edit and delete Teams; everyone sees them. Sarah is an Admin, John a Member. */
final class TeamsTest extends ApiTestCase
{
    private const array SALES = ['id' => 'tmq7x2', 'name' => 'Sales', 'icon' => 'briefcase', 'c' => '#23918A', 'desc' => 'Deals and partnerships'];

    /** The team dialog adds the Team, then moves the Members picked for it. */
    public function testSarahCreatesATeamAndMovesJohnIntoIt(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([
            ['op' => 'put', 'type' => 'teams', 'id' => 'tmq7x2', 'data' => self::SALES],
            ['op' => 'put', 'type' => 'members', 'id' => 'm3', 'data' => [...$this->record('members', 'm3'), 'team' => 'tmq7x2']],
        ]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame(self::SALES, $this->record('teams', 'tmq7x2'));
        self::assertSame('tmq7x2', $this->record('members', 'm3')['team']);
    }

    public function testTheOwnerEditsATeam(): void
    {
        $this->signIn();
        $design = ['id' => 'design', 'name' => 'Brand & Design', 'icon' => 'pen-tool', 'c' => '#C54B78', 'desc' => 'Brand, product design and research'];

        $body = $this->sync([['op' => 'put', 'type' => 'teams', 'id' => 'design', 'data' => $design]]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame($design, $this->record('teams', 'design'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function teamChanges(): iterable
    {
        yield 'create a Team' => [['op' => 'put', 'type' => 'teams', 'id' => 'tmq7x2', 'data' => self::SALES]];
        yield 'edit a Team' => [['op' => 'put', 'type' => 'teams', 'id' => 'eng', 'data' => [...self::SALES, 'id' => 'eng']]];
        yield 'delete a Team' => [['op' => 'delete', 'type' => 'teams', 'id' => 'eng']];
    }

    /** @param array<string, mixed> $op */
    #[\PHPUnit\Framework\Attributes\DataProvider('teamChanges')]
    public function testJohnAMemberChangesNoTeams(array $op): void
    {
        $this->signIn('john@gr8rstudio.com');
        $before = $this->getJson('/api/bootstrap')['data']['teams'];

        $body = $this->sync([$op]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'Only Owners and Admins manage Teams.'], $body);
        self::assertSame($before, $this->getJson('/api/bootstrap')['data']['teams']);
    }

    /** People and Projects are not deleted with their Team: they have none afterwards. */
    public function testDeletingATeamLeavesItsMembersAndProjectsWithoutOne(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'delete', 'type' => 'teams', 'id' => 'design']]);

        self::assertSame(['ok' => true], $body);
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertSame(['eng', 'mkt', 'product'], array_column($data['teams'], 'id'));
        self::assertSame(['m2' => '', 'm4' => ''], array_intersect_key(array_column($data['members'], 'team', 'id'), ['m2' => 0, 'm4' => 0]));
        self::assertNotContains('design', array_column($data['members'], 'team'));
        self::assertNotContains('design', array_column($data['projects'], 'team'));
        self::assertSame('', array_column($data['projects'], 'team', 'id')['p1']);
    }

    /** The frontend clears the Team of its Members and Projects itself: those puts follow the delete. */
    public function testSarahDeletesATeamAsTheFrontendSendsIt(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $data = $this->getJson('/api/bootstrap')['data'];
        $ops = [['op' => 'delete', 'type' => 'teams', 'id' => 'design']];
        foreach (['members', 'projects'] as $collection) {
            foreach ($data[$collection] as $r) {
                if ('design' === $r['team']) {
                    $ops[] = ['op' => 'put', 'type' => $collection, 'id' => $r['id'], 'data' => [...$r, 'team' => '']];
                }
            }
        }

        $body = $this->sync($ops);

        self::assertSame(['ok' => true], $body);
        self::assertSame('', $this->record('members', 'm4')['team']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidTeams(): iterable
    {
        yield 'empty name' => [['name' => ''], 'The Team\'s "name" must be a non-empty string.'];
        yield 'overlong name' => [['name' => str_repeat('x', 121)], 'The Team\'s "name" must be a string of at most 120 characters.'];
        yield 'colour by name' => [['c' => 'teal'], 'The Team\'s "c" must be a colour like #5A67D8.'];
        yield 'no description' => [['desc' => null], 'The Team\'s "desc" must be a string.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTeams')]
    public function testInvalidTeamsAreRefused(array $changes, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'teams', 'id' => 'tmq7x2', 'data' => [...self::SALES, ...$changes]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
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

    /** @return array<string, mixed>|null the record as /api/bootstrap shows it */
    private function record(string $collection, string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data'][$collection], null, 'id')[$id] ?? null;
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }
}
