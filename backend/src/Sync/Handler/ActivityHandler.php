<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Activity;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The Activity feed. The client logs its own changes (ADR 0003); the server keeps each entry as
 * the signed-in Member's, about Projects they see, and never changes or deletes it.
 */
final readonly class ActivityHandler implements CollectionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
    ) {
    }

    public function type(): string
    {
        return 'activity';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $f = new Fields($data, 'Activity entry');
        $task = $f->nullableString('task', 44);
        $project = $f->nullableString('project', 44);
        $entry = new Activity($id, $actor->getWorkspace(), $actor->getId(), $f->nonEmptyString('verb'), $task, $project, $f->timestamp('at'), $f->string('extra'));
        $logged = $this->em->find(Activity::class, $id);
        if (null !== $logged && $logged->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The id "%s" is already used.', $id));
        }
        if (null !== $logged) {
            // Append-only: what was logged stays; the client may send an entry again.
            return [];
        }
        $this->assertSees($actor, $this->find(Task::class, $actor, $task)?->getProject());
        $this->assertSees($actor, $this->find(Project::class, $actor, $project));
        // The entry is the signed-in Member's, whoever the client names (ADR 0003).
        $this->em->persist($entry);

        return [];
    }

    /**
     * Append-only: an entry is never deleted, and asking is not an error. The frontend never
     * deletes Activity itself, but undo restores S.data as it was, so the batch after an undo
     * deletes the entries logged since; refusing them would refuse the undo.
     */
    public function delete(Member $actor, string $id): void
    {
    }

    /**
     * The Task or Project of the actor's Workspace with this id, if it still exists. An entry may
     * name one that is gone: it was deleted after the change was logged, before the batch was sent.
     *
     * @template T of Task|Project
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function find(string $class, Member $actor, ?string $id): Task|Project|null
    {
        $record = null === $id ? null : $this->em->find($class, $id);

        return $record?->getWorkspace() === $actor->getWorkspace() ? $record : null;
    }

    private function assertSees(Member $actor, ?Project $project): void
    {
        if (null !== $project && !$this->access->sees($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not log Activity about the Project "%s".', $project->getId()));
        }
    }
}
