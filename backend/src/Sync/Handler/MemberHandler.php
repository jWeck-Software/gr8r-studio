<?php

namespace App\Sync\Handler;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
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
    private const array ROLES = ['Owner', 'Admin', 'Member', 'Guest'];

    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceAccess $access,
        private WorkspaceRecords $records,
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
            'role' => $fields->oneOf('role', self::ROLES),
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

    public function delete(Member $actor, string $id): void
    {
    }

    /**
     * A Member the Workspace doesn't have yet is someone invited by email: they have no User until
     * they accept.
     *
     * @param array{name: string, title: string, c: string, role: string, team: string} $changes
     */
    private function invite(Member $actor, string $id, Fields $fields, array $changes): void
    {
        self::refuse($this->access->whyNotInvite($actor, $changes['role']));
        if ('invited' !== $fields->string('status')) {
            throw SyncRejected::invalid('A new Member is invited: their "status" must be "invited".');
        }
        $email = mb_strtolower($fields->string('email', 180));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw SyncRejected::invalid('The Member\'s "email" must be an email address.');
        }
        if (null !== $this->em->getRepository(Member::class)->findOneBy(['workspace' => $actor->getWorkspace(), 'email' => $email])) {
            throw SyncRejected::invalid(\sprintf('%s is already a Member of this Workspace.', $email));
        }
        $member = new Member($id, $actor->getWorkspace(), $changes['name'], $email, $changes['role'], $changes['team'], $changes['title'], $changes['c'], 'invited', null, $fields->string('tz', 80));
        $this->em->persist($member);
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
