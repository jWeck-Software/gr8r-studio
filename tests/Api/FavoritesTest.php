<?php

namespace App\Tests\Api;

/** Favorites belong to the Member who starred: `fav` in a record is the signed-in Member's star. */
final class FavoritesTest extends ApiTestCase
{
    public function testTheDemoOwnerHasTheSeededFavoritesAndOthersHaveNone(): void
    {
        $this->signIn();
        self::assertSame([['p1', 'p2'], ['t3']], $this->favorites());

        $this->signInAs('sarah@gr8rstudio.com');
        self::assertSame([[], []], $this->favorites());
    }

    public function testStarringAProjectStarsItOnlyForTheMemberWhoStarred(): void
    {
        $this->signInAs('sarah@gr8rstudio.com');
        $this->star('projects', 'p3', true);
        $this->star('projects', 'p1', true);
        self::assertSame([['p1', 'p3'], []], $this->favorites());

        $this->signInAs(self::OWNER_EMAIL);
        self::assertSame([['p1', 'p2'], ['t3']], $this->favorites());
    }

    public function testUnstarringATaskUnstarsItOnlyForTheMemberWhoUnstarred(): void
    {
        $this->signInAs('sarah@gr8rstudio.com');
        $this->star('tasks', 't3', true);

        $this->signInAs(self::OWNER_EMAIL);
        $this->star('tasks', 't3', false);
        self::assertSame([['p1', 'p2'], []], $this->favorites());

        $this->signInAs('sarah@gr8rstudio.com');
        self::assertSame([[], ['t3']], $this->favorites());
    }

    private function signInAs(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    private function star(string $type, string $id, bool $fav): void
    {
        $record = array_column($this->getJson('/api/bootstrap')['data'][$type], null, 'id')[$id];
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => $type, 'id' => $id, 'data' => [...$record, 'fav' => $fav]]]]);
        self::assertResponseStatusCodeSame(200);
    }

    /** @return array{list<string>, list<string>} the starred Project ids and Task ids, as /api/bootstrap shows them */
    private function favorites(): array
    {
        $data = $this->getJson('/api/bootstrap')['data'];
        $starred = static fn (array $records): array => array_values(array_column(array_filter($records, static fn (array $r): bool => $r['fav']), 'id'));

        return [$starred($data['projects']), $starred($data['tasks'])];
    }
}
