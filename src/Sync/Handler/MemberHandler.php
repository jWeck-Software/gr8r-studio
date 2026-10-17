<?php

namespace App\Sync\Handler;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
use App\Entity\MemberStatus;
use App\Entity\Role;
use App\Invite\Invites;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\Team;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Members: their Role and Team, their own name, title and avatar colour. Email, status, last
 * active and time zone are the server's: what the client sends for them is ignored.
 */
final readonly class MemberHandler implements CollectionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceAccess $access,
        private WorkspaceRecords $records,
        private Invites $invites,
    ) {
    }

    public function type(): string
    {
        return 'members';
    }

    public function put(Member $actor, string $id, array $data): array
    {
        $fields = new Fields($data, 'Member');
        $changes = [
            'name' => $fields->nonEmptyString('name', 120),
            'title' => $fields->string('title', 120),
            'c' => $fields->colour('c'),
            'role' => Role::from($fields->oneOf('role', Role::values())),
            'team' => $fields->string('team', 44),
        ];
        $this->assertTeam($actor, $changes['team']);

        $member = $this->records->find(Member::class, $actor, $id);
        if (null === $member) {
            $this->invite($actor, $id, $fields, $changes);

            return [];
        }
        self::refuse($this->access->whyNotChange($actor, $member, $changes));
        $member->update($changes);

        return [];
    }

    /**
     * Removes the Member from the Workspace: their Tasks become unassigned, they leave every
     * Project (as its lead too), and their favorites go with them. Their User stays, without a
     * Workspace. Changed through the entity manager, so later ops of the same batch see it.
     */
    public function delete(Member $actor, string $id): void
    {
        $member = $this->em->find(Member::class, $id);
        if (null === $member || $member->getWorkspace() !== $actor->getWorkspace()) {
            return;
        }
        self::refuse($this->access->whyNotRemove($actor, $member));
        foreach ($this->em->getRepository(Task::class)->findBy(['assignee' => $member]) as $task) {
            $task->unassign();
        }
        foreach ($this->em->getRepository(Project::class)->findBy(['workspace' => $actor->getWorkspace()]) as $project) {
            $project->dropMember($id);
        }
        $this->invites->forget($member);
        $this->em->remove($member);
    }

    /**
     * A Member the Workspace doesn't have yet is someone invited by email: they have no User until
     * they accept the Invite they are sent.
     *
     * @param array{name: string, title: string, c: string, role: Role, team: string} $changes
     */
    private function invite(Member $actor, string $id, Fields $fields, array $changes): void
    {
        self::refuse($this->access->whyNotInvite($actor, $changes['role']));
        if (MemberStatus::Invited->value !== $fields->string('status')) {
            throw SyncRejected::invalid('A new Member is invited: their "status" must be "invited".');
        }
        $email = mb_strtolower($fields->string('email', 180));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw SyncRejected::invalid('The Member\'s "email" must be an email address.');
        }
        if (null !== $this->em->getRepository(Member::class)->findOneBy(['workspace' => $actor->getWorkspace(), 'email' => $email])) {
            throw SyncRejected::invalid(\sprintf('%s is already a Member of this Workspace.', $email));
        }
        $member = new Member($id, $actor->getWorkspace(), $changes['name'], $email, $changes['role'], $changes['team'], $changes['title'], $changes['c'], MemberStatus::Invited, null, $fields->string('tz', 80));
        $this->em->persist($member);
        $this->invites->send($actor, $member);
    }

    private static function refuse(?string $refusal): void
    {
        if (null !== $refusal) {
            throw SyncRejected::forbidden($refusal);
        }
    }

    /** A Member is in one of the Workspace's Teams, or in none (''). */
    private function assertTeam(Member $actor, string $team): void
    {
        if ('' === $team) {
            return;
        }
        $stored = $this->em->find(Team::class, $team);
        if (null === $stored || $stored->getWorkspace() !== $actor->getWorkspace()) {
            throw SyncRejected::invalid(\sprintf('The Member\'s team "%s" is not a Team of this Workspace.', $team));
        }
    }
}
