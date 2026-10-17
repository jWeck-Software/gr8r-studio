<?php

namespace App\Notification;

/**
 * Which Notifications a put produces (ADR 0003: the server derives them). Pure: records in the
 * frontend's shape in, Notices out; the caller stores them for recipients who can see the Task.
 *
 * - A Task whose `assignee` changes to someone notifies the new assignee ("assigned you").
 * - A new Comment notifies every Member it mentions as `@Full Name` ("mentioned you in") and the
 *   Task's assignee ("commented on"); a mentioned assignee gets only the Mention. Changed and
 *   restored Comments (an `$old` record) notify nobody.
 * - Nobody is notified of their own doing.
 */
final class NotificationRules
{
    /**
     * @param array<string, mixed>|null $old   the Task as stored before the put, null for a new one
     * @param array<string, mixed>      $new   the Task as put
     * @param string                    $today YYYY-MM-DD, for the due date in the snippet
     *
     * @return list<Notice>
     */
    public static function taskPut(string $actor, ?array $old, array $new, string $today): array
    {
        if (null === $new['assignee'] || ($old['assignee'] ?? null) === $new['assignee']) {
            return [];
        }

        return self::notTo($actor, [new Notice($new['assignee'], 'assign', $actor, $new['id'], 'assigned you', self::assignmentSnippet($new, $today))]);
    }

    /**
     * @param array<string, mixed>|null $old       the Comment as stored before the put, null for a new one
     * @param array<string, mixed>      $new       the Comment as put
     * @param array<string, mixed>      $task      the Task the Comment is on
     * @param array<string, string>     $memberIds the Workspace's Member ids by full name
     *
     * @return list<Notice>
     */
    public static function commentPut(string $actor, ?array $old, array $new, array $task, array $memberIds): array
    {
        if (null !== $old) {
            return [];
        }
        $notices = [];
        $mentioned = self::mentioned($new['text'], $memberIds);
        foreach ($mentioned as $id) {
            $notices[] = new Notice($id, 'mention', $actor, $task['id'], 'mentioned you in', $new['text']);
        }
        if (null !== $task['assignee'] && !\in_array($task['assignee'], $mentioned, true)) {
            $notices[] = new Notice($task['assignee'], 'comment', $actor, $task['id'], 'commented on', $new['text']);
        }

        return self::notTo($actor, $notices);
    }

    /**
     * The ids of the Members mentioned as `@Full Name`, as the frontend highlights them. Longer
     * names are matched first and taken out of the text, so "@Ann Lee" does not also mention Ann.
     *
     * @param array<string, string> $memberIds
     *
     * @return list<string>
     */
    private static function mentioned(string $text, array $memberIds): array
    {
        $names = array_map(strval(...), array_keys($memberIds));
        usort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $ids = [];
        foreach ($names as $name) {
            if ('' !== $name && str_contains($text, '@'.$name)) {
                $ids[] = $memberIds[$name];
                $text = str_replace('@'.$name, "\0", $text);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Nobody is notified of their own doing.
     *
     * @param list<Notice> $notices
     *
     * @return list<Notice>
     */
    private static function notTo(string $actor, array $notices): array
    {
        return array_values(array_filter($notices, static fn (Notice $n): bool => $n->recipient !== $actor));
    }

    /**
     * When the Task is due and how urgent it is, like "Due today · Medium priority", or its title
     * when it has neither.
     *
     * @param array<string, mixed> $task
     */
    private static function assignmentSnippet(array $task, string $today): string
    {
        $parts = [];
        if (null !== $task['due']) {
            $days = (int) (new \DateTimeImmutable($today))->diff(new \DateTimeImmutable($task['due']))->format('%r%a');
            $parts[] = match (true) {
                0 === $days => 'Due today',
                1 === $days => 'Due tomorrow',
                -1 === $days => 'Due yesterday',
                $days > 1 => \sprintf('Due in %d days', $days),
                default => \sprintf('Overdue by %d days', -$days),
            };
        }
        if ('none' !== $task['priority']) {
            $parts[] = ucfirst($task['priority']).' priority';
        }

        return [] === $parts ? $task['title'] : implode(' · ', $parts);
    }
}
