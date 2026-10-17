<?php

namespace App\Notification;

use App\Access\ProjectAccess;
use App\Entity\Comment;
use App\Entity\Member;
use App\Entity\Notification;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Stores the Notifications a put produces (NotificationRules) for the recipients who can see the
 * Task; nobody learns about a Private project through a Notification.
 */
final readonly class Notifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private ClockInterface $clock,
    ) {
    }

    /** @param array<string, mixed>|null $old the Task as it was before the put, null when it is new */
    public function taskPut(Member $actor, ?array $old, Task $task): void
    {
        $today = $this->clock->now()->format('Y-m-d');
        $this->store($actor, $task, NotificationRules::taskPut($actor->getId(), $old, $task->toRecord(), $today));
    }

    /** @param array<string, mixed>|null $old the Comment as it was before the put, null when it is new */
    public function commentPut(Member $actor, ?array $old, Comment $comment): void
    {
        $task = $comment->getTask();
        $this->store($actor, $task, NotificationRules::commentPut($actor->getId(), $old, $comment->toRecord(), $task->toRecord(), $this->memberIds($actor)));
    }

    /** @param list<Notice> $notices */
    private function store(Member $actor, Task $task, array $notices): void
    {
        $at = (int) $this->clock->now()->format('Uv');
        foreach ($notices as $notice) {
            $recipient = $this->em->find(Member::class, $notice->recipient);
            if (null === $recipient || $recipient->getWorkspace() !== $actor->getWorkspace() || !$this->access->sees($recipient, $task->getProject())) {
                continue;
            }
            $this->em->persist(new Notification('n'.bin2hex(random_bytes(8)), $actor->getWorkspace(), $recipient, $notice->type, $notice->by, $notice->task, null, $notice->text, $notice->snippet, $at));
        }
    }

    /** @return array<string, string> the Workspace's Member ids by full name */
    private function memberIds(Member $actor): array
    {
        $rows = $this->em->createQuery('SELECT m.id, m.name FROM '.Member::class.' m WHERE m.workspace = :ws')
            ->setParameter('ws', $actor->getWorkspace())
            ->getScalarResult();

        return array_column($rows, 'id', 'name');
    }
}
