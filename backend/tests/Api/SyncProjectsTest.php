<?php

namespace App\Tests\Api;

final class SyncProjectsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function testMemberCreatesAProject(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'pq7x2', 'data' => self::newProject()]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ok' => true], $body);
        self::assertRecord([...self::newProject(), 'seq' => 200], $this->project('pq7x2'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidProjects(): iterable
    {
        yield 'missing name' => [array_diff_key(self::newProject(), ['name' => 0]), 'The Project has no "name".'];
        yield 'unknown status' => [self::newProject(['status' => 'paused']), 'The Project\'s "status" must be one of planning, active, risk, hold, complete.'];
        yield 'malformed key' => [self::newProject(['key' => 'cr-1']), '"cr-1" is not a Project key like WEB.'];
        yield 'unknown lead' => [self::newProject(['lead' => 'm99']), 'The Project\'s lead "m99" is not a Member of this Workspace.'];
        yield 'unknown member' => [self::newProject(['members' => ['m1', 'm99']]), 'The Project\'s member "m99" is not a Member of this Workspace.'];
        yield 'milestone without date' => [self::newProject(['milestones' => [['name' => 'Kickoff']]]), 'The Milestone has no "date".'];
    }

    /** @param array<string, mixed> $data */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProjects')]
    public function testInvalidProjectsAreRefused(array $data, string $message): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'pq7x2', 'data' => $data]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
        self::assertNull($this->project('pq7x2'));
    }

    public function testProjectKeyMustBeUniqueInTheWorkspace(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'pq7x2', 'data' => self::newProject(['key' => 'WEB'])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Project key WEB is already used.'], $body);
    }

    public function testMemberEditsAProjectAndItsSnapshotSeqIsIgnored(): void
    {
        $p1 = $this->project('p1');

        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p1', 'data' => [...$p1, 'name' => 'Website Relaunch', 'seq' => 999]]]);

        self::assertResponseStatusCodeSame(200);
        $saved = $this->project('p1');
        self::assertSame(['Website Relaunch', 'WEB', 200], [$saved['name'], $saved['key'], $saved['seq']]);
    }

    public function testDeletingAProjectDeletesItsTasksAndTakesItOutOfTheProjectOrder(): void
    {
        $body = $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'p7']]);

        self::assertSame(['ok' => true], $body);
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5'], array_column($data['projects'], 'id'));
        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5'], $data['projOrder']);
        self::assertSame([], array_values(array_filter(array_column($data['tasks'], 'project'), static fn (string $p): bool => 'p7' === $p)));
        self::assertCount(46, $data['tasks']);
    }

    public function testATaskMovedOutOfAProjectSurvivesTheProjectsDeletionInTheSameBatch(): void
    {
        $t47 = array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id')['t47'];

        $this->sync([
            ['op' => 'delete', 'type' => 'projects', 'id' => 'p7'],
            ['op' => 'put', 'type' => 'tasks', 'id' => 't47', 'data' => [...$t47, 'project' => 'p1', 'key' => 'WEB-201']],
        ]);

        self::assertResponseStatusCodeSame(200);
        $tasks = array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id');
        self::assertSame(['p1', 'WEB-201'], [$tasks['t47']['project'], $tasks['t47']['key']]);
    }

    public function testDeletingAnUnknownProjectIsANoOp(): void
    {
        $body = $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'pnothere']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ok' => true], $body);
    }

    public function testMemberReordersTheSidebarForTheWorkspace(): void
    {
        $body = $this->sync([['op' => 'set', 'type' => 'projOrder', 'data' => ['p3', 'p1', 'p2', 'p4', 'p5', 'p7']]]);

        self::assertSame(['ok' => true], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');
        self::assertSame(['p3', 'p1', 'p2', 'p4', 'p5', 'p7'], $this->getJson('/api/bootstrap')['data']['projOrder']);
    }

    public function testANewProjectAndItsPlaceInTheOrderArriveInOneBatch(): void
    {
        $this->sync([
            ['op' => 'put', 'type' => 'projects', 'id' => 'pq7x2', 'data' => self::newProject()],
            ['op' => 'set', 'type' => 'projOrder', 'data' => ['pq7x2', 'p1', 'p2', 'p3', 'p4', 'p5', 'p7']],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['pq7x2', 'p1', 'p2', 'p3', 'p4', 'p5', 'p7'], $this->getJson('/api/bootstrap')['data']['projOrder']);
    }

    public function testProjectOrderIgnoresIdsThatAreNotProjects(): void
    {
        $this->sync([['op' => 'set', 'type' => 'projOrder', 'data' => ['p2', 'pgone', 'p1', 'p3', 'p4', 'p5', 'p7', 'p2']]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['p2', 'p1', 'p3', 'p4', 'p5', 'p7'], $this->getJson('/api/bootstrap')['data']['projOrder']);
    }

    public function testProjectOrderMustBeAListOfIds(): void
    {
        $body = $this->sync([['op' => 'set', 'type' => 'projOrder', 'data' => ['p1' => 1]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Project order must be a list of Project ids.'], $body);
    }

    public function testProjectOrderIsSetNotPut(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'projOrder', 'id' => 'p1', 'data' => ['id' => 'p1']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('"put" is not an op for projOrder; use set.', $body['message']);
    }

    public function testArchivingAProjectKeepsWhenItWasArchivedAndRestoringClearsIt(): void
    {
        $p3 = $this->project('p3');

        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p3', 'data' => [...$p3, 'archived' => true, 'archivedAt' => 1792238400000]]]);
        self::assertSame([true, 1792238400000], [$this->project('p3')['archived'], $this->project('p3')['archivedAt']]);

        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p3', 'data' => [...$p3, 'archived' => false, 'archivedAt' => null]]]);
        self::assertFalse($this->project('p3')['archived']);
        self::assertArrayNotHasKey('archivedAt', $this->project('p3'));
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the Project as /api/bootstrap shows it */
    private function project(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')[$id] ?? null;
    }

    /**
     * A Project as the frontend's createProject() makes it.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function newProject(array $changes = []): array
    {
        return array_merge([
            'id' => 'pq7x2',
            'key' => 'CR',
            'name' => 'Customer Research',
            'icon' => 'folder',
            'color' => 'indigo',
            'status' => 'planning',
            'team' => 'product',
            'lead' => 'm2',
            'due' => '2026-11-16',
            'start' => '2026-10-17',
            'fav' => false,
            'members' => ['m1', 'm2'],
            'desc' => 'No description yet.',
            'milestones' => [],
            'last' => 0,
        ], $changes);
    }
}
