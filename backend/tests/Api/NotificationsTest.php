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
