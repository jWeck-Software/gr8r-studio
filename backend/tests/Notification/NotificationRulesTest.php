<?php

namespace App\Tests\Notification;

use App\Notification\Notice;
use App\Notification\NotificationRules;
use PHPUnit\Framework\TestCase;

/**
 * Which Notifications a put produces, decided without the database. "Today" is 2026-10-17;
 * m1 is Tanjim Islam, m2 Sarah Chen, m3 John Carter.
 */
final class NotificationRulesTest extends TestCase
{
    private const string TODAY = '2026-10-17';
    private const array MEMBERS = ['Tanjim Islam' => 'm1', 'Sarah Chen' => 'm2', 'John Carter' => 'm3'];

    public function testAssigningATaskToSomeoneNotifiesThem(): void
    {
        $notices = NotificationRules::taskPut('m1', self::task(['assignee' => 'm3']), self::task(['assignee' => 'm2']), self::TODAY);

        self::assertEquals([new Notice('m2', 'assign', 'm1', 't4', 'assigned you', 'Due today · Medium priority')], $notices);
    }

    public function testTakingATaskOneselfNotifiesNobody(): void
    {
        $notices = NotificationRules::taskPut('m1', self::task(), self::task(['assignee' => 'm1']), self::TODAY);

        self::assertSame([], $notices);
    }

    public function testUnassigningATaskNotifiesNobody(): void
    {
        $notices = NotificationRules::taskPut('m1', self::task(), self::task(['assignee' => null]), self::TODAY);

        self::assertSame([], $notices);
    }

    public function testANewTaskAssignedToSomeoneNotifiesThem(): void
    {
        $notices = NotificationRules::taskPut('m1', null, self::task(['priority' => 'none']), self::TODAY);

        self::assertEquals([new Notice('m3', 'assign', 'm1', 't4', 'assigned you', 'Due today')], $notices);
    }

    /** Commenting bumps the Task's `updated`; that and any other change is not an assignment. */
    public function testChangingATaskWithoutChangingItsAssigneeNotifiesNobody(): void
    {
        $notices = NotificationRules::taskPut('m1', self::task(), self::task(['updated' => 1792238460000, 'priority' => 'urgent', 'title' => 'Ship navigation']), self::TODAY);

        self::assertSame([], $notices);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function assignmentSnippets(): iterable
    {
        yield 'due tomorrow, urgent' => [['due' => '2026-10-18', 'priority' => 'urgent'], 'Due tomorrow · Urgent priority'];
        yield 'due in six days' => [['due' => '2026-10-23', 'priority' => 'high'], 'Due in 6 days · High priority'];
        yield 'due yesterday' => [['due' => '2026-10-16', 'priority' => 'low'], 'Due yesterday · Low priority'];
        yield 'overdue' => [['due' => '2026-10-07', 'priority' => 'low'], 'Overdue by 10 days · Low priority'];
        yield 'no due date' => [['due' => null, 'priority' => 'high'], 'High priority'];
        yield 'no priority' => [['due' => '2026-10-23', 'priority' => 'none'], 'Due in 6 days'];
        yield 'neither' => [['due' => null, 'priority' => 'none'], 'Finalize navigation'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('assignmentSnippets')]
    public function testTheAssignmentSaysWhenTheTaskIsDueAndHowUrgentItIs(array $changes, string $snippet): void
    {
        $notices = NotificationRules::taskPut('m1', self::task(), self::task(['assignee' => 'm2', ...$changes]), self::TODAY);

        self::assertSame($snippet, $notices[0]->snippet);
    }

    public function testMentioningSomeoneInACommentNotifiesThem(): void
    {
        $notices = NotificationRules::commentPut('m1', null, self::comment(['text' => 'Ship it @Sarah Chen']), self::task(['assignee' => 'm1']), self::MEMBERS);

        self::assertEquals([new Notice('m2', 'mention', 'm1', 't4', 'mentioned you in', 'Ship it @Sarah Chen')], $notices);
    }

    /** "@Ann Lee" names Ann Lee, not also Ann, whichever order the Members come in. */
    public function testAMentionNamesTheMemberWithTheLongestMatchingName(): void
    {
        $members = ['Ann' => 'm4', 'Ann Lee' => 'm5', 'Tanjim Islam' => 'm1'];

        $notices = NotificationRules::commentPut('m1', null, self::comment(['text' => '@Ann Lee and @Ann, please check.']), self::task(['assignee' => 'm1']), $members);

        self::assertEquals([
            new Notice('m5', 'mention', 'm1', 't4', 'mentioned you in', '@Ann Lee and @Ann, please check.'),
            new Notice('m4', 'mention', 'm1', 't4', 'mentioned you in', '@Ann Lee and @Ann, please check.'),
        ], $notices);
        self::assertEquals(['m5'], array_map(static fn (Notice $n): string => $n->recipient, NotificationRules::commentPut('m1', null, self::comment(['text' => 'Over to you, @Ann Lee.']), self::task(['assignee' => 'm1']), $members)));
    }

    public function testCommentingOnATaskAssignedToSomeoneElseNotifiesTheAssignee(): void
    {
        $notices = NotificationRules::commentPut('m1', null, self::comment(), self::task(['assignee' => 'm3']), self::MEMBERS);

        self::assertEquals([new Notice('m3', 'comment', 'm1', 't4', 'commented on', 'Looks good.')], $notices);
    }

    /** One Notification per Member: an assignee who is also mentioned gets the Mention. */
    public function testAMentionedAssigneeGetsOnlyTheMention(): void
    {
        $notices = NotificationRules::commentPut('m1', null, self::comment(['text' => '@John Carter over to you']), self::task(['assignee' => 'm3']), self::MEMBERS);

        self::assertEquals([new Notice('m3', 'mention', 'm1', 't4', 'mentioned you in', '@John Carter over to you')], $notices);
    }

    /** An edited or reacted-to Comment, or one restored by undo, was announced when it was new. */
    public function testOnlyANewCommentNotifies(): void
    {
        $old = self::comment(['by' => 'm2', 'text' => '@John Carter over to you']);

        $notices = NotificationRules::commentPut('m1', $old, [...$old, 're' => ['👍' => ['m1']]], self::task(['assignee' => 'm3']), self::MEMBERS);

        self::assertSame([], $notices);
    }

    public function testMentioningOneselfOrCommentingOnOnesOwnTaskNotifiesNobody(): void
    {
        $notices = NotificationRules::commentPut('m1', null, self::comment(['text' => 'Note to self, @Tanjim Islam']), self::task(['assignee' => 'm1']), self::MEMBERS);

        self::assertSame([], $notices);
    }

    /**
     * A Comment in the frontend's shape.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function comment(array $changes = []): array
    {
        return [...['id' => 'cx7k2m9', 'task' => 't4', 'by' => 'm1', 'at' => 1792238400000, 'text' => 'Looks good.', 're' => []], ...$changes];
    }

    /**
     * A Task in the frontend's shape, as far as the rules read it.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function task(array $changes = []): array
    {
        return [...['id' => 't4', 'title' => 'Finalize navigation', 'assignee' => 'm3', 'priority' => 'medium', 'due' => '2026-10-17', 'updated' => 1792238400000], ...$changes];
    }
}
