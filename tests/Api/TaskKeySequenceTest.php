<?php

namespace App\Tests\Api;

/**
 * The frontend numbers new Tasks from the Project's `seq` (its nextKey() takes seq + 1),
 * so the snapshot reports the highest Task key number in use, and at least 200.
 */
final class TaskKeySequenceTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function testASeededProjectStartsNumberingAfter200(): void
    {
        self::assertSame(200, $this->project('p1')['seq']);
    }

    public function testNumberingContinuesAfterTheHighestTaskKeyEvenWhenThatTaskIsArchived(): void
    {
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => [
            'id' => 'xk3j9q2',
            'key' => 'WEB-305',
            'project' => 'p1',
            'title' => 'Draft cookie banner copy',
            'status' => 'todo',
            'assignee' => null,
            'priority' => 'none',
            'due' => null,
            'start' => null,
            'labels' => [],
            'subtasks' => [],
            'attachments' => [],
            'deps' => [],
            'desc' => '',
            'estimate' => null,
            'created' => 1792238400000,
            'updated' => 1792238400000,
            'order' => 49,
            'fav' => false,
            'recur' => null,
            'archived' => true,
        ]]]]);
        self::assertResponseStatusCodeSame(200);

        self::assertSame(305, $this->project('p1')['seq']);
        self::assertSame(200, $this->project('p2')['seq']);
    }

    /** @return array<string, mixed> the Project as /api/bootstrap shows it */
    private function project(string $id): array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')[$id];
    }
}
