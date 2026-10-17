<?php

namespace App\Sync\Handler;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Team;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/** Teams: created, edited and deleted by Owners and Admins. */
final readonly class TeamHandler implements CollectionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceAccess $access,
        private WorkspaceRecords $records,
    ) {
    }

    public function type(): string
    {
        return 'teams';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $f = new Fields($data, 'Team');
        $record = ['name' => $f->nonEmptyString('name', 120), 'icon' => $f->string('icon', 60), 'c' => $f->colour('c'), 'desc' => $f->string('desc')];
        $team = $this->records->find(Team::class, $actor, $id);
        $this->authorize($actor);
        if (null === $team) {
            $team = new Team($id, $actor->getWorkspace());
            $this->em->persist($team);
        }
        $team->update($record);

        return [];
    }

    /**
     * People and Projects are not deleted with their Team; they have none afterwards. Changed
     * through the entity manager, so later ops of the same batch see it.
     */
    public function delete(Member $actor, string $id): void
    {
        $team = $this->em->find(Team::class, $id);
        if (null === $team || $team->getWorkspace() !== $actor->getWorkspace()) {
            return;
        }
        $this->authorize($actor);
        $byWorkspace = ['workspace' => $actor->getWorkspace()];
        foreach ([...$this->em->getRepository(Member::class)->findBy($byWorkspace), ...$this->em->getRepository(Project::class)->findBy($byWorkspace)] as $inTeam) {
            $inTeam->leaveTeam($id);
        }
        $this->em->remove($team);
    }

    private function authorize(Member $actor): void
    {
        if (!$this->access->manages($actor)) {
            throw SyncRejected::forbidden('Only Owners and Admins manage Teams.');
        }
    }
}
