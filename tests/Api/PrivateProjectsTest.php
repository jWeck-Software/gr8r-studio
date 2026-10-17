<?php

namespace App\Tests\Api;

/**
 * The demo's Private project is Customer Portal (p6, key CP) with its one Task t49; its
 * Members are Marcus (m6) and Lena (m7). Everyone else, the Owner included, can't see it.
 */
final class PrivateProjectsTest extends ApiTestCase
{
    public function testItsMembersSeeThePrivateProjectAndItsTasks(): void
    {
        $this->signIn('marcus@gr8rstudio.com');

        $data = $this->getJson('/api/bootstrap')['data'];

        $p6 = array_column($data['projects'], null, 'id')['p6'];
        self::assertSame(['CP', 'Customer Portal', true, ['m6', 'm7']], [$p6['key'], $p6['name'], $p6['private'], $p6['members']]);
        self::assertSame(['CP-103', 'Billing API contract'], array_values(array_intersect_key(array_column($data['tasks'], null, 'id')['t49'], ['key' => 0, 'title' => 0])));
        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7'], $data['projOrder']);
    }

    /** @return iterable<string, array{string}> */
    public static function nonMembers(): iterable
    {
        yield 'the Owner' => [self::OWNER_EMAIL];
        yield 'an Admin' => ['sarah@gr8rstudio.com'];
        yield 'a Member' => ['john@gr8rstudio.com'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonMembers')]
    public function testOthersDoNotSeeThePrivateProjectNorItsTasksNorItsPlaceInTheOrder(string $email): void
    {
        $this->signIn($email);

        $data = $this->getJson('/api/bootstrap')['data'];

        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5', 'p7'], array_column($data['projects'], 'id'));
        self::assertNotContains('t49', array_column($data['tasks'], 'id'));
        self::assertNotContains('p6', array_column($data['tasks'], 'project'));
        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5', 'p7'], $data['projOrder']);
    }

    public function testItsMembersChangeThePrivateProject(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $p6 = $this->record('projects', 'p6');

        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p6', 'data' => [...$p6, 'status' => 'active']]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('active', $this->record('projects', 'p6')['status']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function writesByNonMembers(): iterable
    {
        $p6 = ['id' => 'p6', 'key' => 'CP', 'name' => 'Mine now', 'icon' => 'building-2', 'color' => 'teal', 'status' => 'active', 'team' => 'eng', 'lead' => 'm6',
            'due' => null, 'start' => null, 'fav' => false, 'members' => ['m2', 'm6', 'm7'], 'private' => true, 'desc' => '', 'milestones' => [], 'last' => 0];
        $task = ['id' => 'xk3j9q2', 'key' => 'CP-201', 'project' => 'p6', 'title' => 'Peek', 'status' => 'todo', 'assignee' => null, 'priority' => 'none',
            'due' => null, 'start' => null, 'labels' => [], 'subtasks' => [], 'attachments' => [], 'deps' => [], 'desc' => '', 'estimate' => null,
            'created' => 1792238400000, 'updated' => 1792238400000, 'order' => 50, 'fav' => false, 'recur' => null];

        yield 'edit the Project' => [['op' => 'put', 'type' => 'projects', 'id' => 'p6', 'data' => $p6], 'You may not change the Project "p6".'];
        yield 'delete the Project' => [['op' => 'delete', 'type' => 'projects', 'id' => 'p6'], 'You may not change the Project "p6".'];
        yield 'add a Task to it' => [['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => $task], 'You may not change the Tasks of the Project "p6".'];
        yield 'move its Task out' => [['op' => 'put', 'type' => 'tasks', 'id' => 't49', 'data' => [...$task, 'id' => 't49', 'project' => 'p1', 'key' => 'WEB-201']], 'You may not change the Tasks of the Project "p6".'];
        yield 'delete its Task' => [['op' => 'delete', 'type' => 'tasks', 'id' => 't49'], 'You may not change the Tasks of the Project "p6".'];
    }

    /** @param array<string, mixed> $op */
    #[\PHPUnit\Framework\Attributes\DataProvider('writesByNonMembers')]
    public function testOthersCannotWriteToThePrivateProject(array $op, string $message): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([$op]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('marcus@gr8rstudio.com');
        self::assertSame('Customer Portal', $this->record('projects', 'p6')['name']);
        self::assertSame('p6', $this->record('tasks', 't49')['project']);
        self::assertNull($this->record('tasks', 'xk3j9q2'));
    }

    public function testReorderingTheSidebarKeepsTheProjectsOthersCannotSeeInPlace(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->sync([['op' => 'set', 'type' => 'projOrder', 'data' => ['p7', 'p1', 'p2', 'p3', 'p5', 'p4']]]);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/logout');
        $this->signIn('marcus@gr8rstudio.com');
        self::assertSame(['p7', 'p1', 'p2', 'p3', 'p5', 'p6', 'p4'], $this->getJson('/api/bootstrap')['data']['projOrder']);
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the record as /api/bootstrap shows it */
    private function record(string $collection, string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data'][$collection], null, 'id')[$id] ?? null;
    }
}
