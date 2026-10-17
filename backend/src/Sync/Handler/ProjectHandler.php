<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\SavedView;
use App\Entity\Task;
use App\File\Files;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Projects: created, edited, duplicated, archived, restored and deleted.
 *
 * Two Projects may share a key: Task keys are unique per Project, and refusing a key in use
 * would reveal Private projects to those who can't see them.
 */
final readonly class ProjectHandler implements CollectionHandler
{
    private const array STATUSES = ['planning', 'active', 'risk', 'hold', 'complete'];

    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private TaskHandler $tasks,
        private WorkspaceRecords $records,
        private Files $files,
    ) {
    }

    public function type(): string
    {
        return 'projects';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $ws = $actor->getWorkspace();
        $fields = new Fields($data, 'Project');
        $record = self::record($fields);
        $fav = $fields->bool('fav');

        foreach (array_filter([$record['lead']]) as $lead) {
            $this->assertMember($actor, $lead, 'lead');
        }
        foreach ($record['members'] as $member) {
            $this->assertMember($actor, $member, 'member');
        }

        $project = $this->records->find(Project::class, $actor, $id);
        if (null !== $project && $this->access->sees($actor, $project) && self::record(new Fields($project->toRecord(), 'Project')) === $record) {
            // Nothing but the star changed: anyone who sees the Project may star it, Guests included.
            $actor->star('projects', $id, $fav);

            return [];
        }
        $this->authorize($actor, $id, $project);
        if (null === $project) {
            $project = new Project($id, $ws);
            $this->em->persist($project);
        }
        $project->update($record);
        $actor->star('projects', $id, $fav);

        return [];
    }

    public function delete(Member $actor, string $id): void
    {
        $project = $this->em->find(Project::class, $id);
        if (null === $project || $project->getWorkspace() !== $actor->getWorkspace()) {
            return;
        }
        $this->authorize($actor, $id, $project);
        $this->deleteContents($project);
        $ws = $project->getWorkspace();
        $ws->setProjOrder(array_values(array_diff($ws->getProjOrder(), [$id])));
        $this->em->remove($project);
    }

    /**
     * Everything that lives inside a Project goes with it: its Tasks, their Comments, its Files, Events
     * and Saved views. Removed through the entity manager, not only by the database's cascade, so
     * later ops of the same batch see them gone.
     */
    private function deleteContents(Project $project): void
    {
        foreach ($this->em->getRepository(Task::class)->findBy(['project' => $project]) as $task) {
            $this->tasks->remove($task);
        }
        $this->files->removeAllOf($project);
        foreach ([...$this->em->getRepository(Event::class)->findBy(['project' => $project]), ...$this->em->getRepository(SavedView::class)->findBy(['project' => $project])] as $content) {
            $this->em->remove($content);
        }
    }

    private function authorize(Member $actor, string $id, ?Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not change the Project "%s".', $id));
        }
    }

    private function assertMember(Member $actor, string $id, string $as): void
    {
        $member = $this->em->find(Member::class, $id);
        if (null === $member || $member->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The Project\'s %s "%s" is not a Member of this Workspace.', $as, $id));
        }
    }

    /**
     * The Project as stored; `fav` is the actor's star and `seq` is derived by the snapshot.
     *
     * @return array<string, mixed>
     */
    private static function record(Fields $f): array
    {
        $milestones = [];
        foreach ($f->objectList('milestones') as $milestone) {
            $m = new Fields($milestone, 'Milestone');
            $milestones[] = ['name' => $m->string('name'), 'date' => $m->nullableDate('date')];
        }

        return [
            'key' => self::projectKey($f->string('key')),
            'name' => $f->string('name'),
            'icon' => $f->string('icon'),
            'color' => $f->string('color'),
            'status' => $f->oneOf('status', self::STATUSES),
            'team' => $f->string('team'),
            'lead' => $f->nullableString('lead', 44),
            'due' => $f->nullableDate('due'),
            'start' => $f->nullableDate('start'),
            'members' => $f->stringList('members'),
            'desc' => $f->string('desc'),
            'milestones' => $milestones,
            'last' => $f->nullableInt('last'),
            'private' => $f->optionalBool('private'),
            'archived' => $f->optionalBool('archived'),
            'archivedAt' => $f->optionalTimestamp('archivedAt'),
        ];
    }

    private static function projectKey(string $key): string
    {
        return 1 === preg_match('/^[A-Z0-9]{1,10}$/', $key) ? $key : throw SyncRejected::invalid(\sprintf('"%s" is not a Project key like WEB.', $key));
    }
}
