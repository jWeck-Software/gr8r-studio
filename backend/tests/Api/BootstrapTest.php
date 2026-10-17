<?php

namespace App\Tests\Api;

final class BootstrapTest extends ApiTestCase
{
    public function testBootstrapWithoutSessionIsUnauthenticated(): void
    {
        $body = $this->getJson('/api/bootstrap');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthenticated', $body['error']);
    }

    public function testSignedInMemberReceivesTheirWorkspaceInTheFrontendShape(): void
    {
        $this->signIn();

        $body = $this->getJson('/api/bootstrap');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['data', 'prefs', 'views'], array_keys($body));
        self::assertSame('m1', $body['data']['me']);
        self::assertSame(['name' => 'Gr8r Studio', 'url' => 'gr8rstudio', 'c' => '#1D1C1A', 'brand' => true], $body['data']['ws']);
        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5', 'p7'], $body['data']['projOrder']);
    }

    public function testWorkspaceMembersIncludeInvitedOnes(): void
    {
        $this->signIn();

        $members = $this->getJson('/api/bootstrap')['data']['members'];

        self::assertSame(['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8'], array_column($members, 'id'));
        self::assertSame([
            'id' => 'm2',
            'name' => 'Sarah Chen',
            'email' => 'sarah@gr8rstudio.com',
            'role' => 'Admin',
            'team' => 'design',
            'title' => 'Design Lead',
            'c' => '#C54B78',
            'status' => 'active',
            'last' => 4,
            'tz' => 'New York',
        ], $members[1]);
        self::assertSame(['status' => 'invited', 'last' => null], array_intersect_key($members[7], ['status' => 0, 'last' => 0]));
    }

    public function testProjectsCarryTheirMilestonesAndMembersWithDatesRelativeToToday(): void
    {
        $this->signIn();

        $projects = $this->getJson('/api/bootstrap')['data']['projects'];

        self::assertSame(['p1', 'p2', 'p3', 'p4', 'p5', 'p7'], array_column($projects, 'id'));
        self::assertRecord([
            'id' => 'p2',
            'key' => 'MOB',
            'name' => 'Mobile App',
            'icon' => 'smartphone',
            'color' => 'blue',
            'status' => 'active',
            'team' => 'eng',
            'lead' => 'm3',
            'due' => '2026-12-08',
            'start' => '2026-09-02',
            'fav' => true,
            'members' => ['m1', 'm3', 'm4', 'm6', 'm7'],
            'desc' => 'Native iOS and Android client for field teams with offline sync, push notifications, and biometric sign-in.',
            'milestones' => [
                ['name' => 'Beta build', 'date' => '2026-10-26'],
                ['name' => 'App Store submission', 'date' => '2026-12-01'],
            ],
            'last' => 48,
            'seq' => 200,
        ], $projects[1]);
    }

    public function testTasksCarrySubtasksAttachmentsAndDependencies(): void
    {
        $this->signIn();

        $tasks = $this->getJson('/api/bootstrap')['data']['tasks'];

        self::assertCount(48, $tasks);
        $byId = array_column($tasks, null, 'id');
        self::assertRecord([
            'id' => 't3',
            'key' => 'WEB-109',
            'project' => 'p1',
            'title' => 'Create homepage wireframes',
            'status' => 'review',
            'assignee' => 'm2',
            'priority' => 'high',
            'due' => '2026-10-18',
            'start' => '2026-10-11',
            'labels' => ['design'],
            'subtasks' => [
                ['id' => 's1', 'title' => 'Desktop layout', 'done' => true],
                ['id' => 's2', 'title' => 'Tablet layout', 'done' => true],
                ['id' => 's3', 'title' => 'Mobile layout', 'done' => true],
                ['id' => 's4', 'title' => 'Annotate interactions', 'done' => false],
            ],
            'attachments' => [
                ['id' => 'a1', 'name' => 'homepage-wireframes-v3.fig', 'type' => 'fig', 'size' => '4.2 MB', 'by' => 'm2', 'at' => 1792220400000],
                ['id' => 'a2', 'name' => 'nav-audit-findings.pdf', 'type' => 'pdf', 'size' => '860 KB', 'by' => 'm2', 'at' => 1791758400000],
            ],
            'deps' => [],
            'desc' => '<p>Low-fidelity wireframes for the new homepage covering <b>desktop, tablet, and mobile</b> breakpoints.</p><ul><li>Hero with product value proposition</li><li>Customer logos and proof points</li><li>Feature overview linking to product pages</li></ul><p>Reference the navigation audit for IA decisions.</p>',
            'estimate' => '3d',
            'created' => 1791201600000,
            'updated' => 1792236120000,
            'order' => 3,
            'fav' => true,
            'recur' => null,
        ], $byId['t3']);
        self::assertSame(['t3', 't5'], $byId['t8']['deps']);
        self::assertSame('Weekly', $byId['t15']['recur']);
    }

    public function testDoneTasksCarryWhenTheyWereCompleted(): void
    {
        $this->signIn();

        $byId = array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id');

        self::assertSame(1792216800000, $byId['t1']['completedAt']);
        self::assertSame(1789819200000, $byId['t1']['created']);
        self::assertArrayNotHasKey('completedAt', $byId['t4']);
        self::assertArrayNotHasKey('archived', $byId['t4']);
        self::assertArrayNotHasKey('prevStatus', $byId['t4']);
    }

    public function testDemoOnlyCollectionsHaveFixedValues(): void
    {
        $this->signIn();

        $data = $this->getJson('/api/bootstrap')['data'];

        self::assertSame(['w1', 'w2', 'w3'], array_column($data['workspaces'], 'id'));
        self::assertSame(['se1', 'se2', 'se3'], array_column($data['sessions'], 'id'));
        self::assertSame([
            ['id' => 'INV-2026-009', 'date' => '2026-09-24', 'amt' => '$96.00', 'st' => 'Paid'],
            ['id' => 'INV-2026-008', 'date' => '2026-08-25', 'amt' => '$96.00', 'st' => 'Paid'],
            ['id' => 'INV-2026-007', 'date' => '2026-07-25', 'amt' => '$80.00', 'st' => 'Paid'],
            ['id' => 'INV-2026-006', 'date' => '2026-06-25', 'amt' => '$80.00', 'st' => 'Paid'],
        ], $data['invoices']);
        self::assertFalse($data['tfa']);
    }

    public function testCollectionsOfLaterTicketsAreEmpty(): void
    {
        $this->signIn();

        $data = $this->getJson('/api/bootstrap')['data'];

        foreach (['files', 'events', 'savedViews', 'recentSearches'] as $collection) {
            self::assertSame([], $data[$collection], $collection);
        }
    }

    /** Until Teams are stored, the frontend falls back to its built-in Teams when the key is missing. */
    public function testTeamsAreLeftToTheFrontend(): void
    {
        $this->signIn();

        self::assertArrayNotHasKey('teams', $this->getJson('/api/bootstrap')['data']);
    }

    public function testObjectsStayObjectsWhenEmpty(): void
    {
        $this->signIn();
        $this->client->request('GET', '/api/bootstrap');

        $body = json_decode((string) $this->client->getResponse()->getContent());

        self::assertEquals(new \stdClass(), $body->prefs);
        self::assertEquals(new \stdClass(), $body->views);
        self::assertEquals(new \stdClass(), $body->data->notifPrefs);
    }
}
