<?php

namespace App\Tests\Api;

/**
 * A Saved view is a named set of filters on one Project that everyone who sees the Project can
 * open; whoever may change the Project saves, renames and deletes them. Customer Portal (p6) is the
 * demo's Private project, with Marcus (m6) and Lena (m7) as its Members.
 */
final class SavedViewsTest extends ApiTestCase
{
    private const array QUEUE = ['id' => 'vq7x2', 'project' => 'p1', 'name' => 'Design review queue', 'type' => 'board', 'filters' => [['f' => 'assignee', 'op' => 'is', 'v' => ['m4', 'none']], ['f' => 'labels', 'op' => 'not', 'v' => ['backend']]]];

    public function testTheDemoHasASavedViewOnTheWebsiteRedesign(): void
    {
        $this->signIn();

        self::assertRecord(
            [['id' => 'v1', 'project' => 'p1', 'name' => 'High priority', 'type' => 'list', 'filters' => [['f' => 'priority', 'op' => 'is', 'v' => ['urgent', 'high']]]]],
            $this->getJson('/api/bootstrap')['data']['savedViews'],
        );
    }

    public function testSarahSavesAViewAndJohnOpensIt(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'put', 'type' => 'savedViews', 'id' => 'vq7x2', 'data' => self::QUEUE]]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertRecord(self::QUEUE, $this->savedView('vq7x2'));
    }

    public function testJohnRenamesTheSavedViewAndTheOwnerDeletesIt(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $v1 = $this->savedView('v1');

        $this->sync([['op' => 'put', 'type' => 'savedViews', 'id' => 'v1', 'data' => [...$v1, 'name' => 'Urgent and high']]]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo(self::OWNER_EMAIL);
        self::assertSame('Urgent and high', $this->savedView('v1')['name']);
        self::assertSame(['ok' => true], $this->sync([['op' => 'delete', 'type' => 'savedViews', 'id' => 'v1']]));
        self::assertNull($this->savedView('v1'));
    }

    public function testDeletingAProjectDeletesItsSavedViews(): void
    {
        $this->signIn();

        $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'p1'], ['op' => 'delete', 'type' => 'savedViews', 'id' => 'v1']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->getJson('/api/bootstrap')['data']['savedViews']);
    }

    public function testSavedViewsOfAPrivateProjectAreSeenAndChangedOnlyByItsMembers(): void
    {
        $this->signIn('lena@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'savedViews', 'id' => 'vq7x2', 'data' => [...self::QUEUE, 'project' => 'p6']]]);
        self::assertResponseStatusCodeSame(200);

        $this->switchTo('sarah@gr8rstudio.com');

        self::assertNull($this->savedView('vq7x2'));
        foreach ([['op' => 'put', 'type' => 'savedViews', 'id' => 'vq7x2', 'data' => self::QUEUE], ['op' => 'delete', 'type' => 'savedViews', 'id' => 'vq7x2']] as $op) {
            $body = $this->sync([$op]);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('You may not change the Saved views of the Project "p6".', $body['message']);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function wrongSavedViews(): iterable
    {
        yield 'no name' => [[...self::QUEUE, 'name' => ''], 'The Saved view\'s "name" must be a non-empty string.'];
        yield 'an unknown layout' => [[...self::QUEUE, 'type' => 'gallery'], 'The Saved view\'s "type" must be one of list, board, table, calendar, timeline.'];
        yield 'a filter without values' => [[...self::QUEUE, 'filters' => [['f' => 'priority', 'op' => 'is']]], 'The filter has no "v".'];
        yield 'a filter with an unknown operator' => [[...self::QUEUE, 'filters' => [['f' => 'priority', 'op' => 'like', 'v' => ['high']]]], 'The filter\'s "op" must be one of is, not.'];
        yield 'an unknown Project' => [[...self::QUEUE, 'project' => 'p99'], 'The Saved view\'s Project "p99" does not exist.'];
    }

    /** @param array<string, mixed> $view */
    #[\PHPUnit\Framework\Attributes\DataProvider('wrongSavedViews')]
    public function testASavedViewOfTheWrongShapeIsRefused(array $view, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'savedViews', 'id' => 'vq7x2', 'data' => $view]]);

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

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** @return array<string, mixed>|null the Saved view as /api/bootstrap shows it */
    private function savedView(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['savedViews'], null, 'id')[$id] ?? null;
    }
}
