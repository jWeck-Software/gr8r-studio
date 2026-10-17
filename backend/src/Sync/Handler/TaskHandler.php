<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
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
    ) {
    }

    public function type(): string
    {
        return 'tasks';
    }

    public function put(Member $actor, string $id, array $data): void
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
        if (null !== $task && $this->access->sees($actor, $task->getProject()) && self::record(new Fields($task->toRecord(), 'Task')) === $record) {
            // Nothing but the star changed: anyone who sees the Task may star it, Guests included.
            $actor->star('tasks', $id, $fav);

            return;
        }
        if (null !== $task) {
            $this->authorize($actor, $task->getProject());
        }
        $this->authorize($actor, $project);
        if ($this->keyTaken($project, $record['key'], $id)) {
            throw SyncRejected::invalid(\sprintf('The Task key %s is already used in this Project.', $record['key']));
        }

        if (null === $task) {
            $task = new Task($id, $ws);
            $this->em->persist($task);
        }
        $task->update($record, $project, $assignee);
        $actor->star('tasks', $id, $fav);
    }

    public function delete(Member $actor, string $id): void
    {
        $task = $this->em->find(Task::class, $id);
        if (null !== $task && $task->getWorkspace() === $actor->getWorkspace()) {
            $this->authorize($actor, $task->getProject());
            $this->em->remove($task);
        }
    }

    private function authorize(Member $actor, Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not change the Tasks of the Project "%s".', $project->getId()));
        }
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
