<?php

namespace App\Tests\Api;

/**
 * Notifications are the server's (ADR 0003): made when someone assigns, mentions or comments,
 * never for one's own doing, and each Member sees only their own. "Finalize navigation" (t4)
 * in Website Redesign is John's (m3), due today with high priority.
 */
final class NotificationsTest extends ApiTestCase
{
    private const int NOW = 1792238400000;

    /** The demo's Notifications are the Owner's, as in the frontend's seed. */
    public function testTheDemoOwnerHasNotificationsNewestFirst(): void
    {
        $this->signIn();

        $notifs = $this->getJson('/api/bootstrap')['data']['notifs'];

        self::assertSame(['n10', 'n5', 'n3', 'n9', 'n4', 'n1', 'n2', 'n6', 'n7', 'n8'], array_column($notifs, 'id'));
        self::assertSame([
            'id' => 'n1', 'type' => 'mention', 'by' => 'm2', 'task' => 't3', 'text' => 'mentioned you in',
            'snippet' => 'Updated in v3 — @Tanjim Islam can you review before Thursday?', 'at' => self::NOW - 210 * 60_000, 'read' => false,
        ], $notifs[5]);
        self::assertSame([
            'id' => 'n6', 'type' => 'update', 'by' => null, 'project' => 'p4', 'text' => 'Product Launch is at risk',
            'snippet' => '3 tasks due this week are not started', 'at' => self::NOW - 600 * 60_000, 'read' => false,
        ], $notifs[7]);
        self::assertSame([6, 4], [\count(array_filter($notifs, static fn (array $n): bool => !$n['read'])), \count(array_filter($notifs, static fn (array $n): bool => $n['read']))]);
    }

    public function testAssigningATaskToSomeoneNotifiesThem(): void
    {
        $this->signIn();

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => [...$this->task('t4'), 'assignee' => 'm2']]]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo('sarah@gr8rstudio.com');
        self::assertSame([
            ['type' => 'assign', 'by' => 'm1', 'task' => 't4', 'text' => 'assigned you', 'snippet' => 'Due today · High priority', 'at' => self::NOW, 'read' => false],
        ], $this->notifs());
    }

    public function testAMentionNotifiesTheMentionedMember(): void
    {
        $this->signIn();

        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('Can you review the mega-menu, @Sarah Chen?')]]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo('sarah@gr8rstudio.com');
        self::assertSame([
            ['type' => 'mention', 'by' => 'm1', 'task' => 't4', 'text' => 'mentioned you in', 'snippet' => 'Can you review the mega-menu, @Sarah Chen?', 'at' => self::NOW, 'read' => false],
        ], $this->notifs());
    }

    /** The frontend sends the Task with its `updated` bumped before the Comment: not an assignment. */
    public function testACommentOnATaskAssignedToSomeoneElseNotifiesTheAssigneeOnce(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => [...$this->task('t4'), 'updated' => self::NOW]],
            ['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('Mega-menu copy is in.')],
        ]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame([
            ['type' => 'comment', 'by' => 'm2', 'task' => 't4', 'text' => 'commented on', 'snippet' => 'Mega-menu copy is in.', 'at' => self::NOW, 'read' => false],
        ], $this->notifs());
    }

    public function testOnesOwnAssignmentsMentionsAndCommentsNotifyNobody(): void
    {
        $this->signIn('john@gr8rstudio.com');

        $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 't5', 'data' => [...$this->task('t5'), 'assignee' => 'm3']],
            ['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('Note to self, @John Carter: stub the mega-menu.')],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->notifs());
        $this->switchTo('emma@gr8rstudio.com');
        self::assertSame([], $this->notifs(), 't5 was Emma\'s; taking a Task away is no Notification.');
    }

    public function testEachMemberSeesOnlyTheirOwnNotifications(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('@John Carter and @Emma Wilson, sync at three?')]]);

        $this->switchTo('emma@gr8rstudio.com');
        self::assertSame(['mention'], array_column($this->notifs(), 'type'));
        $this->switchTo('lena@gr8rstudio.com');
        self::assertSame([], $this->notifs());
    }

    /**
     * Customer Portal (p6) is Private to Marcus (m6) and Lena (m7). The Owner, mentioned while he
     * can't see it, finds no Notification about it even after he is added to it.
     */
    public function testNobodyIsNotifiedAboutATaskTheyCannotSee(): void
    {
        $this->signIn('marcus@gr8rstudio.com');

        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('@Tanjim Islam @Lena Fischer contract review is done.', task: 't49')]]);
        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p6', 'data' => [...$this->project('p6'), 'members' => ['m6', 'm7', 'm1']]]]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo('lena@gr8rstudio.com');
        self::assertSame([['mention', 't49']], array_map(static fn (array $n): array => [$n['type'], $n['task']], $this->notifs()));
        $this->switchTo(self::OWNER_EMAIL);
        self::assertNotContains('t49', array_column($this->notifs(), 'task'));
    }

    /** A Member who leaves a Private project no longer sees the Notifications about it. */
    public function testANotificationGoesOutOfSightWithItsTask(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::comment('@Lena Fischer contract review is done.', task: 't49')]]);

        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p6', 'data' => [...$this->project('p6'), 'members' => ['m6']]]]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo('lena@gr8rstudio.com');
        self::assertSame([], $this->notifs());
    }

    /**
     * Sarah deletes her Task t3 and undoes it: Sarah's c2 on it, which mentions the Owner, comes
     * back without notifying him again.
     */
    public function testUndoingADeletionNotifiesNobodyOfTheCommentsItBringsBack(): void
    {
        $this->signIn(self::OWNER_EMAIL);
        $before = $this->getJson('/api/bootstrap')['data']['notifs'];
        $this->switchTo('sarah@gr8rstudio.com');
        $data = $this->getJson('/api/bootstrap')['data'];
        $comments = array_values(array_filter($data['comments'], static fn (array $c): bool => 't3' === $c['task']));
        $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 't3'], ...array_map(static fn (array $c): array => ['op' => 'delete', 'type' => 'comments', 'id' => $c['id']], $comments)]);

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't3', 'data' => array_column($data['tasks'], null, 'id')['t3']], ...array_map(static fn (array $c): array => ['op' => 'put', 'type' => 'comments', 'id' => $c['id'], 'data' => $c], $comments)]);

        self::assertResponseStatusCodeSame(200);
        $this->switchTo(self::OWNER_EMAIL);
        self::assertSame($before, $this->getJson('/api/bootstrap')['data']['notifs']);
    }

    /** The Owner deletes John's t4 and undoes it: the Task comes back to John without telling him it is his again. */
    public function testUndoingTheDeletionOfAnAssignedTaskNotifiesNobody(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $before = $this->notifs();
        $this->switchTo(self::OWNER_EMAIL);
        $t4 = $this->task('t4');
        $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]);

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => $t4]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('m3', $this->task('t4')['assignee']);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame($before, $this->notifs());
    }

    public function testTheRecipientMarksANotificationReadAndUnread(): void
    {
        $notif = $this->notifySarah();

        $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => $notif['id'], 'data' => [...$notif, 'read' => true]]]);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->getJson('/api/bootstrap')['data']['notifs'][0]['read']);
        $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => $notif['id'], 'data' => [...$notif, 'read' => false]]]);
        self::assertFalse($this->getJson('/api/bootstrap')['data']['notifs'][0]['read']);
    }

    public function testMarkingSomeoneElsesNotificationIsForbidden(): void
    {
        $notif = $this->notifySarah();
        $this->switchTo(self::OWNER_EMAIL);

        $body = $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => $notif['id'], 'data' => [...$notif, 'read' => true]]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => \sprintf('The Notification "%s" is not yours.', $notif['id'])], $body);
        $this->switchTo('sarah@gr8rstudio.com');
        self::assertFalse($this->getJson('/api/bootstrap')['data']['notifs'][0]['read']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function changesBesidesRead(): iterable
    {
        yield 'its text' => [['text' => 'fired you']];
        yield 'its snippet' => [['snippet' => 'Due never']];
        yield 'its type' => [['type' => 'mention']];
        yield 'who caused it' => [['by' => 'm3']];
        yield 'its Task' => [['task' => 't5']];
        yield 'a Project' => [['project' => 'p1']];
        yield 'its time' => [['at' => 1792238300000]];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('changesBesidesRead')]
    public function testTheRecipientMayChangeNothingButRead(array $changes): void
    {
        $notif = $this->notifySarah();

        $body = $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => $notif['id'], 'data' => [...$notif, ...$changes, 'read' => true]]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => \sprintf('Only "read" of the Notification "%s" can change.', $notif['id'])], $body);
        self::assertSame($notif, $this->getJson('/api/bootstrap')['data']['notifs'][0]);
    }

    public function testReadMustBeTrueOrFalse(): void
    {
        $notif = $this->notifySarah();

        $body = $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => $notif['id'], 'data' => [...$notif, 'read' => 'yes']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Notification\'s "read" must be true or false.'], $body);
    }

    /** Only the server makes Notifications; one the client made up is dropped, not refused. */
    public function testANotificationTheClientMadeUpIsIgnored(): void
    {
        $this->signIn();
        $before = $this->getJson('/api/bootstrap')['data']['notifs'];

        $body = $this->sync([['op' => 'put', 'type' => 'notifs', 'id' => 'nq0zz', 'data' => ['id' => 'nq0zz', 'type' => 'update', 'by' => null, 'project' => 'p4', 'text' => 'Launch is at risk', 'snippet' => '', 'at' => self::NOW, 'read' => false]]]);

        self::assertSame(['ok' => true], $body);
        self::assertSame($before, $this->getJson('/api/bootstrap')['data']['notifs']);
    }

    /** The frontend never deletes a Notification, but undo restores S.data as it was. */
    public function testDeletingANotificationChangesNothing(): void
    {
        $notif = $this->notifySarah();

        $body = $this->sync([['op' => 'delete', 'type' => 'notifs', 'id' => $notif['id']]]);

        self::assertSame(['ok' => true], $body);
        self::assertSame([$notif], $this->getJson('/api/bootstrap')['data']['notifs']);
    }

    /**
     * Sarah, signed in, with the Notification she got for the Owner assigning her t4.
     *
     * @return array<string, mixed> the Notification as bootstrap shows it
     */
    private function notifySarah(): array
    {
        $this->signIn();
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => [...$this->task('t4'), 'assignee' => 'm2']]]);
        $this->switchTo('sarah@gr8rstudio.com');

        return $this->getJson('/api/bootstrap')['data']['notifs'][0];
    }

    /**
     * A new Comment on t4 as the frontend's postComment() sends it.
     *
     * @return array<string, mixed>
     */
    private static function comment(string $text, string $id = 'cx7k2m9', string $task = 't4'): array
    {
        return ['id' => $id, 'task' => $task, 'by' => 'm1', 'at' => self::NOW, 'text' => $text, 're' => []];
    }

    /**
     * The signed-in Member's Notifications as bootstrap shows them, newest first, without their
     * server-made ids (checked for the id format).
     *
     * @return list<array<string, mixed>>
     */
    private function notifs(): array
    {
        return array_map(static function (array $n): array {
            self::assertMatchesRegularExpression('/^[a-z]{1,3}[a-z0-9-]{1,40}$/', $n['id']);
            unset($n['id']);

            return $n;
        }, $this->getJson('/api/bootstrap')['data']['notifs']);
    }

    /** @return array<string, mixed> the Task as /api/bootstrap shows it */
    private function task(string $id): array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id')[$id];
    }

    /** @return array<string, mixed> the Project as the frontend sends it back */
    private function project(string $id): array
    {
        $project = array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')[$id];
        unset($project['seq']);

        return $project;
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
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
}
