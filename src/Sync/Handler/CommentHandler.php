<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Comment;
use App\Entity\DeletedComment;
use App\Entity\Member;
use App\Entity\Task;
use App\Notification\Notifier;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/** Comments on Tasks and their Reactions. */
final readonly class CommentHandler implements CollectionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private Notifier $notifier,
        private WorkspaceRecords $records,
    ) {
    }

    public function type(): string
    {
        return 'comments';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $ws = $actor->getWorkspace();
        $fields = new Fields($data, 'Comment');
        $record = [
            'task' => $fields->string('task'),
            'by' => $fields->string('by'),
            'at' => $fields->timestamp('at'),
            'text' => $fields->nonEmptyString('text'),
            're' => $fields->stringListMap('re'),
        ];

        $task = $this->em->find(Task::class, $record['task']);
        if (null === $task || $task->getWorkspace() !== $ws) {
            throw SyncRejected::invalid(\sprintf('The Comment\'s Task "%s" does not exist.', $record['task']));
        }
        $this->assertSees($actor, $task);

        $comment = $this->records->find(Comment::class, $actor, $id);
        $deleted = null === $comment ? $this->records->find(DeletedComment::class, $actor, $id) : null;
        if (null !== $deleted) {
            // Undo: the Comment comes back as its author's, then the put applies as to any Comment.
            $comment = $this->restore($actor, $deleted);
        }
        if (null === $comment) {
            $comment = new Comment($id, $ws);
            $this->em->persist($comment);
            // A new Comment is the signed-in Member's, whoever the client names (ADR 0003).
            $comment->update([...$record, 'by' => $actor->getId()], $task);
            $this->notifier->commentPut($actor, null, $comment);

            return [];
        }
        $this->authorizeChange($actor, $comment, $record);
        $old = $comment->toRecord();
        $comment->update($record, $task);
        $this->notifier->commentPut($actor, $old, $comment);

        return [];
    }

    public function delete(Member $actor, string $id): void
    {
        $comment = $this->em->find(Comment::class, $id);
        if (null === $comment || $comment->getWorkspace() !== $actor->getWorkspace()) {
            return;
        }
        // Visibility first: who can't see the Task doesn't learn who wrote the Comment.
        if (!$this->access->sees($actor, $comment->getTask()->getProject())) {
            throw SyncRejected::forbidden(\sprintf('You may not delete the Comment "%s".', $id));
        }
        if ($comment->getBy() !== $actor->getId()) {
            throw SyncRejected::forbidden(\sprintf('Only its author may delete the Comment "%s".', $id));
        }
        $this->remove($comment);
    }

    /**
     * Removes the Comment and keeps what undo needs to put it back as its author's. Through the
     * entity manager, so later ops of the same batch see it gone.
     */
    public function remove(Comment $comment): void
    {
        $this->em->persist(DeletedComment::of($comment));
        $this->em->remove($comment);
    }

    /** Puts a deleted Comment back as it was, its author included, for a Member who sees its Task. */
    private function restore(Member $actor, DeletedComment $deleted): Comment
    {
        $task = $this->em->find(Task::class, $deleted->getTask());
        if (null === $task || $task->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The Comment\'s Task "%s" does not exist.', $deleted->getTask()));
        }
        $this->assertSees($actor, $task);
        $comment = $deleted->restore($task);
        $this->em->remove($deleted);
        $this->em->persist($comment);

        return $comment;
    }

    /**
     * A Comment keeps its Task, author and time. Its author may change the text; everyone,
     * the author included, may only add or remove their own Reactions.
     *
     * @param array{task: string, by: string, at: int, text: string, re: array<string, list<string>>} $record
     */
    private function authorizeChange(Member $actor, Comment $comment, array $record): void
    {
        $stored = $comment->toRecord();
        $mine = $comment->getBy() === $actor->getId();
        $refusal = $mine
            ? \sprintf('You may only add or remove your own Reactions on the Comment "%s".', $comment->getId())
            : \sprintf('On the Comment "%s" by someone else you may only add or remove your own Reactions.', $comment->getId());
        foreach (['task', 'by', 'at'] as $field) {
            if ($stored[$field] !== $record[$field]) {
                throw SyncRejected::forbidden($mine ? \sprintf('A Comment keeps its Task, author and time; "%s" cannot change.', $field) : $refusal);
            }
        }
        if ((!$mine && $stored['text'] !== $record['text']) || !self::onlyOwnReactionsChanged((array) $stored['re'], $record['re'], $actor->getId())) {
            throw SyncRejected::forbidden($refusal);
        }
    }

    /**
     * Whether `$after` differs from `$before` only by the Member's own id under some emoji.
     * Order is ignored, and an emoji with no ids is the same as a missing one.
     *
     * @param array<string, list<string>> $before
     * @param array<string, list<string>> $after
     */
    private static function onlyOwnReactionsChanged(array $before, array $after, string $memberId): bool
    {
        foreach (array_keys($before + $after) as $emoji) {
            $old = $before[$emoji] ?? [];
            $new = $after[$emoji] ?? [];
            $changed = [...array_diff($old, $new), ...array_diff($new, $old)];
            if ([] !== array_diff($changed, [$memberId])) {
                return false;
            }
        }

        return true;
    }

    /** Whoever sees a Task may comment on it and react, Guests included: `sees`, not `mayChange`. */
    private function assertSees(Member $actor, Task $task): void
    {
        if (!$this->access->sees($actor, $task->getProject())) {
            throw SyncRejected::forbidden(\sprintf('You may not comment on the Task "%s".', $task->getId()));
        }
    }
}
