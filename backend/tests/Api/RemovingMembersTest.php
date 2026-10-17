<?php

namespace App\Tests\Api;

/**
 * Owners and Admins remove Members from the Workspace. John (m3) leads Mobile App (p2), works on
 * several Projects and has Tasks assigned.
 */
final class RemovingMembersTest extends ApiTestCase
{
    /** The server unassigns and drops John itself, whatever else the batch carries. */
    public function testRemovingJohnUnassignsHisTasksAndDropsHimFromProjects(): void
    {
        $this->signIn();
        $johns = $this->tasksOf('m3');
        self::assertNotEmpty($johns);

        $body = $this->sync([['op' => 'delete', 'type' => 'members', 'id' => 'm3']]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('sarah@gr8rstudio.com');
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertNotContains('m3', array_column($data['members'], 'id'));
        self::assertSame([], $this->tasksOf('m3'));
        $tasks = array_column($data['tasks'], null, 'id');
        self::assertSame(array_fill_keys($johns, null), array_map(static fn (string $id): ?string => $tasks[$id]['assignee'], array_combine($johns, $johns)));
        $projects = array_column($data['projects'], null, 'id');
        self::assertNull($projects['p2']['lead']);
        self::assertSame([], array_values(array_filter($data['projects'], static fn (array $p): bool => \in_array('m3', $p['members'], true))));
    }

    /**
     * The frontend removes John and, in the same save, the Projects and Tasks it changed: those
     * come after the Member's delete (members sync before projects and tasks) and match what the
     * server did already.
     */
    public function testSarahRemovesJohnAsTheFrontendSendsIt(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $data = $this->getJson('/api/bootstrap')['data'];
        $ops = [['op' => 'delete', 'type' => 'members', 'id' => 'm3']];
        foreach ($data['projects'] as $p) {
            if (\in_array('m3', $p['members'], true) || 'm3' === $p['lead']) {
                $ops[] = ['op' => 'put', 'type' => 'projects', 'id' => $p['id'], 'data' => [...$p, 'members' => array_values(array_diff($p['members'], ['m3'])), 'lead' => 'm3' === $p['lead'] ? null : $p['lead']]];
            }
        }
        foreach ($data['tasks'] as $t) {
            if ('m3' === $t['assignee']) {
                $ops[] = ['op' => 'put', 'type' => 'tasks', 'id' => $t['id'], 'data' => [...$t, 'assignee' => null]];
            }
        }

        $body = $this->sync($ops);

        self::assertSame(['ok' => true], $body);
        self::assertSame([], $this->tasksOf('m3'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function refusedRemovals(): iterable
    {
        yield 'John, a Member, removes Emma' => ['john@gr8rstudio.com', 'm4', 'Only Owners and Admins remove Members.'];
        yield 'Sarah, an Admin, removes the Owner' => ['sarah@gr8rstudio.com', 'm1', 'Only an Owner removes an Owner.'];
        yield 'Sarah removes herself' => ['sarah@gr8rstudio.com', 'm2', 'You cannot remove yourself from the Workspace.'];
        yield 'the Owner removes herself' => [self::OWNER_EMAIL, 'm1', 'You cannot remove yourself from the Workspace.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedRemovals')]
    public function testRemovalsAreRefused(string $email, string $id, string $message): void
    {
        $this->signIn($email);

        $body = $this->sync([['op' => 'delete', 'type' => 'members', 'id' => $id]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
        self::assertContains($id, array_column($this->getJson('/api/bootstrap')['data']['members'], 'id'));
    }

    /** John still signs in, but has no Workspace to work in any more. */
    public function testARemovedMemberHasNoWorkspace(): void
    {
        $this->signIn();
        $this->sync([['op' => 'delete', 'type' => 'members', 'id' => 'm3']]);

        $this->switchTo('john@gr8rstudio.com');
        self::assertResponseStatusCodeSame(200);

        foreach (['/api/me', '/api/bootstrap'] as $path) {
            $body = $this->getJson($path);
            self::assertResponseStatusCodeSame(403);
            self::assertSame(['error' => 'no_workspace', 'message' => 'You are not a Member of any Workspace.'], $body, $path);
        }
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

    /** @return list<string> the ids of the Tasks assigned to the Member, as /api/bootstrap shows them */
    private function tasksOf(string $member): array
    {
        return array_values(array_column(array_filter($this->getJson('/api/bootstrap')['data']['tasks'], static fn (array $t): bool => $member === $t['assignee']), 'id'));
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }
}
