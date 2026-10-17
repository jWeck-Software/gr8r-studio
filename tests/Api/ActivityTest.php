<?php

namespace App\Tests\Api;

/**
 * Activity: the client logs an entry for each change it makes (ADR 0003), the server
 * keeps it as the signed-in Member's and never changes or drops it.
 */
final class ActivityTest extends ApiTestCase
{
    public function testBootstrapCarriesTheActivityNewestFirst(): void
    {
        $this->signIn();

        $activity = $this->getJson('/api/bootstrap')['data']['activity'];

        self::assertSame(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9', 'a12', 'a13', 'a10', 'a11'], array_column($activity, 'id'));
        self::assertSame(['id' => 'a1', 'by' => 'm2', 'verb' => 'moved', 'task' => 't3', 'project' => 'p1', 'at' => 1792236120000, 'extra' => 'to Review'], $activity[0]);
        self::assertSame(['id' => 'a10', 'by' => 'm1', 'verb' => 'created project', 'task' => null, 'project' => 'p4', 'at' => 1790438400000, 'extra' => ''], $activity[11]);
    }

    public function testAMembersChangeIsLoggedAsTheirsWhateverTheClientSays(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => self::entry(['by' => 'm2'])]]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(['id' => 'ax7k2m9', 'by' => 'm1', 'verb' => 'commented on', 'task' => 't4', 'project' => 'p1', 'at' => 1792238400000, 'extra' => ''], $this->entryInBootstrap('ax7k2m9'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidEntries(): iterable
    {
        yield 'no verb' => [['verb' => ''], 'The Activity entry\'s "verb" must be a non-empty string.'];
        yield 'no time' => [['at' => null], 'The Activity entry\'s "at" must be epoch milliseconds.'];
        yield 'a Task that is no id' => [['task' => ['t4']], 'The Activity entry\'s "task" must be a string of at most 44 characters or null.'];
        yield 'no extra' => [['extra' => null], 'The Activity entry\'s "extra" must be a string.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEntries')]
    public function testInvalidEntriesAreRefused(array $changes, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => self::entry($changes)]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    /** Append-only: the client may send an entry again, but what was logged stays as it was. */
    public function testAnEntrySentAgainChangesNothing(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([
            ['op' => 'put', 'type' => 'activity', 'id' => 'a1', 'data' => ['id' => 'a1', 'by' => 'm2', 'verb' => 'deleted', 'task' => null, 'project' => null, 'at' => 0, 'extra' => 'everything']],
        ]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(['id' => 'a1', 'by' => 'm2', 'verb' => 'moved', 'task' => 't3', 'project' => 'p1', 'at' => 1792236120000, 'extra' => 'to Review'], $this->entryInBootstrap('a1'));
    }

    /** Undo restores S.data as it was, so the frontend's batch deletes the entries logged since. */
    public function testDeletingAnEntryKeepsIt(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'delete', 'type' => 'activity', 'id' => 'a4']]);

        self::assertSame(['ok' => true], $body);
        self::assertSame('changed priority of', $this->entryInBootstrap('a4')['verb']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function entriesAboutThePrivateProject(): iterable
    {
        yield 'its Project' => [['task' => null, 'project' => 'p6'], 'You may not log Activity about the Project "p6".'];
        yield 'its Task' => [['task' => 't49', 'project' => 'p1'], 'You may not log Activity about the Project "p6".'];
    }

    /**
     * The Owner is not a Member of the Private project Customer Portal (p6) and its Task t49.
     *
     * @param array<string, mixed> $changes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('entriesAboutThePrivateProject')]
    public function testLoggingActivityAboutAProjectOneCannotSeeIsForbidden(array $changes, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => self::entry($changes)]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
    }

    /** The Task was deleted after the change was logged and before the batch was sent. */
    public function testAnEntryMayNameATaskThatIsGone(): void
    {
        $this->signIn();

        $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => self::entry(['task' => 'tq0zz', 'verb' => 'created'])]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['tq0zz', 'p1'], [$this->entryInBootstrap('ax7k2m9')['task'], $this->entryInBootstrap('ax7k2m9')['project']]);
    }

    public function testActivityInAPrivateProjectIsSeenOnlyByItsMembers(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => self::entry(['task' => 't49', 'project' => 'p6', 'verb' => 'completed'])]]);
        self::assertResponseStatusCodeSame(200);

        $this->switchTo('lena@gr8rstudio.com');
        self::assertSame('m6', $this->entryInBootstrap('ax7k2m9')['by']);
        $this->switchTo(self::OWNER_EMAIL);
        self::assertNull($this->entryInBootstrap('ax7k2m9'));
    }

    /** The frontend's Activity page looks up the Project of every entry that names one. */
    public function testTheActivityOfADeletedProjectIsNoLongerShown(): void
    {
        $this->signIn();

        $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'p1']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['a3', 'a6', 'a8', 'a9', 'a12', 'a10'], array_column($this->getJson('/api/bootstrap')['data']['activity'], 'id'));
    }

    /**
     * A new entry as the frontend's logAct() sends it.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function entry(array $changes = []): array
    {
        return [...['id' => 'ax7k2m9', 'by' => 'm1', 'verb' => 'commented on', 'task' => 't4', 'project' => 'p1', 'at' => 1792238400000, 'extra' => ''], ...$changes];
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** @return array<string, mixed>|null the entry as /api/bootstrap shows it */
    private function entryInBootstrap(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['activity'], null, 'id')[$id] ?? null;
    }
}
