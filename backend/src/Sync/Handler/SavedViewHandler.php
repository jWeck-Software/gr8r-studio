<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\SavedView;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saved views of a Project: saved, renamed and deleted by whoever may change the Project
 * (ProjectAccess::mayChange), so not by Guests. Deleted with their Project.
 */
final readonly class SavedViewHandler implements CollectionHandler
{
    /** The layouts a Project's tab shows Tasks in (taskViewBody() in src/pages/project.js). */
    private const array TYPES = ['list', 'board', 'table', 'calendar', 'timeline'];

    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private WorkspaceRecords $records,
    ) {
    }

    public function type(): string
    {
        return 'savedViews';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $f = new Fields($data, 'Saved view');
        $record = ['name' => $f->nonEmptyString('name', 120), 'type' => $f->oneOf('type', self::TYPES), 'filters' => self::filters($f)];
        $project = $this->em->find(Project::class, $f->string('project', 44));
        if (null === $project || $project->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The Saved view\'s Project "%s" does not exist.', $data['project']));
        }
        $view = $this->records->find(SavedView::class, $actor, $id);
        if (null !== $view) {
            $this->authorize($actor, $view->getProject());
        }
        $this->authorize($actor, $project);
        if (null === $view) {
            $view = new SavedView($id, $actor->getWorkspace(), $project);
            $this->em->persist($view);
        }
        $view->update($record, $project);

        return [];
    }

    public function delete(Member $actor, string $id): void
    {
        $view = $this->em->find(SavedView::class, $id);
        if (null !== $view && $view->getWorkspace() === $actor->getWorkspace()) {
            $this->authorize($actor, $view->getProject());
            $this->em->remove($view);
        }
    }

    /** @return list<array{f: string, op: string, v: list<string>}> */
    private static function filters(Fields $f): array
    {
        $filters = [];
        foreach ($f->objectList('filters') as $filter) {
            $ff = new Fields($filter, 'filter');
            $filters[] = ['f' => $ff->nonEmptyString('f', 40), 'op' => $ff->oneOf('op', ['is', 'not']), 'v' => $ff->stringList('v')];
        }

        return $filters;
    }

    private function authorize(Member $actor, Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw SyncRejected::forbidden(\sprintf('You may not change the Saved views of the Project "%s".', $project->getId()));
        }
    }
}
