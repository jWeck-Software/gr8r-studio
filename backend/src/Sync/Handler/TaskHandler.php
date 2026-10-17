<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Comment;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskKeys;
use App\Notification\Notifier;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\Sync;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/** Tasks: created, changed, moved, archived and deleted by any Member of the Workspace. */
final readonly class TaskHandler implements CollectionHandler
{
    private const array STATUSES = ['backlog', 'todo', 'progress', 'review', 'done'];
    private const array PRIORITIES = ['urgent', 'high', 'medium', 'low', 'none'];

    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private Notifier $notifier,
        private CommentHandler $comments,
    ) {
    }

    public function type(): string
    {
        return 'tasks';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $ws = $actor->getWorkspace();
        $fields = new Fields($data, 'Task');
        $record = self::record($fields);
        $fav = $fields->bool('fav');

        $project = $this->em->find(Project::class, $record['project']);
        if (null === $project || $project->getWorkspace() !== $ws) {
            throw SyncRejected::invalid(\sprintf('The Task\'s Project "%s" does not exist.', $record['project']));
        }
        $assignee = null;
        if (null !== $record['assignee']) {
            $assignee = $this->em->find(Member::class, $record['assignee']);
            if (null === $assignee || $assignee->getWorkspace() !== $ws) {
                throw SyncRejected::invalid(\sprintf('The Task\'s assignee "%s" is not a Member of this Workspace.', $record['assignee']));
            }
        }

        $task = $this->em->find(Task::class, $id);
        if (null !== $task && $task->getWorkspace() !== $ws) {
            throw SyncRejected::invalid(\sprintf('The id "%s" is already used.', $id));
        }
        $untouched = static fn (array $r): array => array_diff_key($r, ['updated' => 0]);
        if (null !== $task && $this->access->sees($actor, $task->getProject()) && $untouched(self::record(new Fields($task->toRecord(), 'Task'))) === $untouched($record)) {
            // Nothing but the star and the time changed: anyone who sees the Task may star it, and
            // commenting on it bumps `updated` (the frontend's logAct), Guests included.
            $task->touch($record['updated']);
            $actor->star('tasks', $id, $fav);

            return [];
        }
        if (null !== $task) {
            $this->authorize($actor, $task->getProject());
        }
        $this->authorize($actor, $project);
        $reply = [];
        if ($this->keyTaken($project, $record['key'], $id)) {
            if (null !== $task) {
                throw SyncRejected::invalid(\sprintf('The Task key %s is already used in this Project.', $record['key']));
            }
            // Two Members numbered a new Task from the same seq: the later one gets the next key (ADR 0003).
            $record['key'] = TaskKeys::next($project->getKey(), $this->keysIn($project));
            $reply = ['keys' => [$id => $record['key']]];
        }

        // Read before the update: the assign rule compares against the Task as it was.
        $old = $task?->toRecord();
        if (null === $task) {
            $task = new Task($id, $ws);
            $this->em->persist($task);
        }
        $task->update($record, $project, $assignee);
        $actor->star('tasks', $id, $fav);
        $this->notifier->taskPut($actor, $old, $task);

        return $reply;
    }

    public function delete(Member $actor, string $id): void
    {
        $task = $this->em->find(Task::class, $id);
        if (null !== $task && $task->getWorkspace() === $actor->getWorkspace()) {
            $this->authorize($actor, $task->getProject());
            $this->remove($task);
        }
    }

    /**
     * Removes the Task and its Comments, which undo can put back as their authors'. Through the
     * entity manager, not only by the database's cascade, so later ops of the same batch see them
     * gone. Activity about the Task stays.
     */
    public function remove(Task $task): void
    {
        foreach ($this->em->getRepository(Comment::class)->findBy(['task' => $task]) as $comment) {
            $this->comments->remove($comment);
        }
        $this->em->remove($task);
    }

    private function authorize(Member $actor, Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not change the Tasks of the Project "%s".', $project->getId()));
        }
    }

    /** @return list<string> */
    private function keysIn(Project $project): array
    {
        return array_column($this->em->createQuery('SELECT t.key FROM '.Task::class.' t WHERE t.project = :project')
            ->setParameter('project', $project)
            ->getScalarResult(), 'key');
    }

    private function keyTaken(Project $project, string $key, string $id): bool
    {
        return null !== $this->em->createQuery('SELECT t.id FROM '.Task::class.' t WHERE t.project = :project AND t.key = :key AND t.id <> :id')
            ->setParameters(['project' => $project, 'key' => $key, 'id' => $id])
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /** @return array<string, mixed> */
    private static function record(Fields $f): array
    {
        $subtasks = [];
        foreach ($f->objectList('subtasks') as $subtask) {
            $s = new Fields($subtask, 'Subtask');
            $subtasks[] = ['id' => $s->nonEmptyString('id'), 'title' => $s->string('title'), 'done' => $s->bool('done')];
        }
        $deps = $f->stringList('deps');
        foreach ($deps as $dep) {
            if (1 !== preg_match(Sync::ID_FORMAT, $dep)) {
                throw SyncRejected::invalid(\sprintf('The Task\'s Dependency "%s" is not a Task id.', $dep));
            }
        }

        return [
            'key' => self::taskKey($f->string('key')),
            'project' => $f->string('project'),
            'title' => $f->string('title'),
            'status' => $f->oneOf('status', self::STATUSES),
            'assignee' => $f->nullableString('assignee'),
            'priority' => $f->oneOf('priority', self::PRIORITIES),
            'due' => $f->nullableDate('due'),
            'start' => $f->nullableDate('start'),
            'labels' => $f->stringList('labels'),
            'subtasks' => $subtasks,
            'attachments' => $f->objectList('attachments'),
            'deps' => $deps,
            'desc' => $f->string('desc'),
            'estimate' => $f->nullableString('estimate', 40),
            'created' => $f->timestamp('created'),
            'updated' => $f->timestamp('updated'),
            'order' => $f->number('order'),
            'recur' => $f->nullableString('recur', 40),
            'completedAt' => $f->optionalTimestamp('completedAt'),
            'prevStatus' => $f->optionalOneOf('prevStatus', self::STATUSES),
            'archived' => $f->optionalBool('archived'),
            'archivedAt' => $f->optionalTimestamp('archivedAt'),
        ];
    }

    private static function taskKey(string $key): string
    {
        return 1 === preg_match('/^[A-Z0-9]{1,10}-\d{1,9}$/', $key) ? $key : throw SyncRejected::invalid(\sprintf('"%s" is not a Task key like WEB-103.', $key));
    }
}
