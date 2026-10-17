<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Project;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Events on a Project's calendar: added, changed, moved to another Project and deleted by whoever
 * may change the Project (ProjectAccess::mayChange), so not by Guests. Deleted with their Project.
 */
final readonly class EventHandler implements CollectionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private WorkspaceRecords $records,
    ) {
    }

    public function type(): string
    {
        return 'events';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $f = new Fields($data, 'Event');
        $record = ['title' => $f->nonEmptyString('title', 200), 'date' => $f->date('date'), 'time' => $f->time('time')];
        $project = $this->em->find(Project::class, $f->string('project', 44));
        if (null === $project || $project->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The Event\'s Project "%s" does not exist.', $data['project']));
        }
        $event = $this->records->find(Event::class, $actor, $id);
        if (null !== $event) {
            $this->authorize($actor, $event->getProject());
        }
        $this->authorize($actor, $project);
        if (null === $event) {
            $event = new Event($id, $actor->getWorkspace(), $project);
            $this->em->persist($event);
        }
        $event->update($record, $project);

        return [];
    }

    public function delete(Member $actor, string $id): void
    {
        $event = $this->em->find(Event::class, $id);
        if (null !== $event && $event->getWorkspace() === $actor->getWorkspace()) {
            $this->authorize($actor, $event->getProject());
            $this->em->remove($event);
        }
    }

    private function authorize(Member $actor, Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not change the Events of the Project "%s".', $project->getId()));
        }
    }
}
