<?php

namespace App\Tests\Api;

final class SyncTasksTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function testMemberCreatesATask(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask()]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ok' => true], $body);
        self::assertRecord(self::newTask(), $this->task('xk3j9q2'));
    }

    public function testMemberChangesATask(): void
    {
        $t4 = $this->task('t4');
        $done = array_merge($t4, ['status' => 'done', 'prevStatus' => 'progress', 'completedAt' => 1792238400000, 'updated' => 1792238400000]);

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => $done]]);

        self::assertResponseStatusCodeSame(200);
        $saved = $this->task('t4');
        self::assertSame(['done', 'progress', 1792238400000, 1792238400000], [$saved['status'], $saved['prevStatus'], $saved['completedAt'], $saved['updated']]);
        self::assertSame('WEB-112', $saved['key']);
    }

    public function testArchivingKeepsTheTaskAndWhenAndRestoringKeepsTheFlag(): void
    {
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['archived' => true, 'archivedAt' => 1792238400000])]]);
        self::assertSame([true, 1792238400000], [$this->task('xk3j9q2')['archived'], $this->task('xk3j9q2')['archivedAt']]);

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['archived' => false, 'archivedAt' => null])]]);
        self::assertFalse($this->task('xk3j9q2')['archived']);
        self::assertArrayNotHasKey('archivedAt', $this->task('xk3j9q2'));
    }

    public function testDragAndDropOrderBetweenTwoTasksIsKept(): void
    {
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['order' => 3.5])]]);

        self::assertSame(3.5, $this->task('xk3j9q2')['order']);
    }

    public function testMemberDeletesATask(): void
    {
        $body = $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]);

        self::assertSame(['ok' => true], $body);
        self::assertNull($this->task('t4'));
    }

    public function testDeletingAnUnknownTaskIsANoOp(): void
    {
        $body = $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 'tnothere']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ok' => true], $body);
    }

    public function testOpsApplyInOrder(): void
    {
        $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask()],
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['title' => 'Renamed'])],
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xa1', 'data' => self::newTask(['id' => 'xa1', 'key' => 'WEB-161'])],
            ['op' => 'delete', 'type' => 'tasks', 'id' => 'xa1'],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Renamed', $this->task('xk3j9q2')['title']);
        self::assertNull($this->task('xa1'));
    }

    public function testARefusedOpRollsBackTheWholeBatch(): void
    {
        $body = $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask()],
            ['op' => 'delete', 'type' => 'tasks', 'id' => 't4'],
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xa1', 'data' => self::newTask(['id' => 'xa1', 'key' => 'WEB-161', 'status' => 'started'])],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 2, 'message' => 'The Task\'s "status" must be one of backlog, todo, progress, review, done.'], $body);
        self::assertNull($this->task('xk3j9q2'));
        self::assertNotNull($this->task('t4'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidTasks(): iterable
    {
        yield 'missing title' => [array_diff_key(self::newTask(), ['title' => 0]), 'The Task has no "title".'];
        yield 'unknown priority' => [self::newTask(['priority' => 'asap']), 'The Task\'s "priority" must be one of urgent, high, medium, low, none.'];
        yield 'date not a calendar date' => [self::newTask(['due' => '2026-02-30']), 'The Task\'s "due" must be a date YYYY-MM-DD or null.'];
        yield 'timestamp as a string' => [self::newTask(['created' => '1792238400000']), 'The Task\'s "created" must be epoch milliseconds.'];
        yield 'labels not a list' => [self::newTask(['labels' => 'design']), 'The Task\'s "labels" must be a list.'];
        yield 'subtask without done' => [self::newTask(['subtasks' => [['id' => 's1', 'title' => 'Draft']]]), 'The Subtask has no "done".'];
        yield 'dependency not a Task id' => [self::newTask(['deps' => ['T 3']]), 'The Task\'s Dependency "T 3" is not a Task id.'];
        yield 'malformed key' => [self::newTask(['key' => 'web 160']), '"web 160" is not a Task key like WEB-103.'];
        yield 'unknown assignee' => [self::newTask(['assignee' => 'm99']), 'The Task\'s assignee "m99" is not a Member of this Workspace.'];
        yield 'id differs from the op' => [self::newTask(['id' => 'xother']), 'The record\'s id must match the op\'s id.'];
    }

    /** @param array<string, mixed> $data */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTasks')]
    public function testInvalidTasksAreRefused(array $data, string $message): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => $data]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
        self::assertNull($this->task('xk3j9q2'));
    }

    public function testTaskForAnUnknownProjectIsRefused(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['project' => 'pnothere'])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The Task\'s Project "pnothere" does not exist.', $body['message']);
    }

    public function testTaskKeyMustBeUniqueWithinItsProject(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['key' => 'WEB-103'])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Task key WEB-103 is already used in this Project.'], $body);
    }

    public function testTwoNewTasksInOneBatchCannotShareAKey(): void
    {
        $body = $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask()],
            ['op' => 'put', 'type' => 'tasks', 'id' => 'xa1', 'data' => self::newTask(['id' => 'xa1'])],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $body['op']);
        self::assertNull($this->task('xk3j9q2'));
    }

    public function testTheSameKeyMayBeUsedInAnotherProject(): void
    {
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::newTask(['key' => 'WEB-103', 'project' => 'p2'])]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('p2', $this->task('xk3j9q2')['project']);
    }

    public function testATaskKeepsItsKeyWhenItIsSavedAgain(): void
    {
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't1', 'data' => array_merge($this->task('t1'), ['title' => 'Audit the navigation'])]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['WEB-103', 'Audit the navigation'], [$this->task('t1')['key'], $this->task('t1')['title']]);
    }

    public function testUnknownTypeIsRefused(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'gizmos', 'id' => 'g1', 'data' => ['id' => 'g1']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'Unknown type "gizmos".'], $body);
    }

    public function testTasksCannotBeSetAsAWhole(): void
    {
        $body = $this->sync([['op' => 'set', 'type' => 'tasks', 'data' => []]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('"set" is not an op for tasks; use put or delete.', $body['message']);
    }

    public function testIdsMustMatchTheIdFormat(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 'Task_1', 'data' => self::newTask(['id' => 'Task_1'])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The id must be a string matching /^[a-z]{1,3}[a-z0-9-]{1,40}$/.', $body['message']);
    }

    public function testABodyWithoutOpsIsRefused(): void
    {
        $body = $this->postJson('/api/sync', ['put' => []]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'The body must be {"ops": [...]}.'], $body);
    }

    public function testSyncWithoutSessionIsUnauthenticated(): void
    {
        $this->client->request('POST', '/api/logout');

        $body = $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthenticated', $body['error']);
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the Task as /api/bootstrap shows it */
    private function task(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id')[$id] ?? null;
    }

    /**
     * A Task as the frontend creates it in the Website Redesign Project.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function newTask(array $changes = []): array
    {
        return array_merge([
            'id' => 'xk3j9q2',
            'key' => 'WEB-160',
            'project' => 'p1',
            'title' => 'Draft cookie banner copy',
            'status' => 'todo',
            'assignee' => 'm5',
            'priority' => 'medium',
            'due' => '2026-10-24',
            'start' => '2026-10-20',
            'labels' => ['content'],
            'subtasks' => [['id' => 'sq81mz0', 'title' => 'Check legal wording', 'done' => false]],
            'attachments' => [],
            'deps' => ['t3'],
            'desc' => '<p>Short and friendly.</p>',
            'estimate' => '2h',
            'created' => 1792238400000,
            'updated' => 1792238400000,
            'order' => 49,
            'fav' => false,
            'recur' => null,
        ], $changes);
    }
}
