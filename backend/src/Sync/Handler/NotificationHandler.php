<?php

namespace App\Sync\Handler;

use App\Entity\Member;
use App\Entity\Notification;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/** Notifications are the server's (ADR 0003); their recipient only marks them read or unread. */
final readonly class NotificationHandler implements CollectionHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function type(): string
    {
        return 'notifs';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $read = (new Fields($data, 'Notification'))->bool('read');
        $notification = $this->em->find(Notification::class, $id);
        if (null === $notification || $notification->getWorkspace() !== $actor->getWorkspace()) {
            // Only the server makes Notifications (ADR 0003): one the client made up is dropped.
            return [];
        }
        if ($notification->getRecipient() !== $actor) {
            throw SyncRejected::forbidden(\sprintf('The Notification "%s" is not yours.', $id));
        }
        if (self::fixed($notification->toRecord()) !== self::fixed($data)) {
            throw SyncRejected::forbidden(\sprintf('Only "read" of the Notification "%s" can change.', $id));
        }
        $notification->markRead($read);

        return [];
    }

    /**
     * A Notification is never deleted, and asking is not an error: the frontend never deletes one
     * itself, but undo restores S.data as it was.
     */
    public function delete(Member $actor, string $id): void
    {
    }

    /**
     * What the recipient cannot change; `task` and `project` are missing when not set.
     *
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private static function fixed(array $record): array
    {
        $fixed = [];
        foreach (['type', 'by', 'task', 'project', 'text', 'snippet', 'at'] as $field) {
            $fixed[$field] = $record[$field] ?? null;
        }

        return $fixed;
    }
}
