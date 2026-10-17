<?php

namespace App\Access;

use App\Entity\Member;
use App\Entity\MemberStatus;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Who may change the Workspace's people and settings: Members' Roles and Teams, Invites, removing
 * Members, Teams, and the Workspace's name and colour. Owners and Admins manage them; changing an
 * Owner's Role is the Owners' own business, as deleting the Workspace is, and a Workspace always
 * keeps one Owner. Everyone edits their own name, title and avatar colour.
 *
 * Every refusal comes back as the reason to show, null when the change is allowed, so the sync
 * handlers ask here and the rules live in one place (as ProjectAccess does for Projects).
 */
final readonly class WorkspaceAccess
{
    private const string OWNERS_ONLY = 'Only an Owner changes an Owner\'s Role or makes someone an Owner.';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** Whether the Member manages people, Teams and the Workspace's settings: Owners and Admins. */
    public function manages(Member $member): bool
    {
        return \in_array($member->getRole(), [Role::Owner, Role::Admin], true);
    }

    /** Why the actor may not invite someone into the Workspace with this Role, or null when they may. */
    public function whyNotInvite(Member $actor, Role $role): ?string
    {
        if (!$this->manages($actor)) {
            return 'Only Owners and Admins invite people.';
        }

        return Role::Owner === $role && Role::Owner !== $actor->getRole() ? self::OWNERS_ONLY : null;
    }

    /** Why the actor may not delete the Workspace, or null when they may: only its Owners do. */
    public function whyNotDeleteWorkspace(Member $actor): ?string
    {
        return Role::Owner === $actor->getRole() ? null : 'Only an Owner deletes the Workspace.';
    }

    /**
     * Why the actor may not remove the Member from the Workspace, or null when they may. Nobody
     * removes themselves, so the last Owner always stays.
     */
    public function whyNotRemove(Member $actor, Member $member): ?string
    {
        return match (true) {
            !$this->manages($actor) => 'Only Owners and Admins remove Members.',
            $actor === $member => 'You cannot remove yourself from the Workspace.',
            Role::Owner === $member->getRole() && Role::Owner !== $actor->getRole() => 'Only an Owner removes an Owner.',
            default => null,
        };
    }

    /**
     * Why the actor may not change these fields of the Member, or null when they may.
     *
     * @param array{name: string, title: string, c: string, role: Role, team: string} $changes
     */
    public function whyNotChange(Member $actor, Member $member, array $changes): ?string
    {
        $stored = $member->changeable();
        $changed = array_keys(array_filter($changes, static fn (string|Role $value, string $field): bool => $stored[$field] !== $value, \ARRAY_FILTER_USE_BOTH));
        if ([] !== array_intersect($changed, ['name', 'title', 'c']) && $actor !== $member) {
            return 'Only the Member themselves changes their name, title and avatar colour.';
        }
        if (\in_array('role', $changed, true) && !$this->manages($actor)) {
            return 'Only Owners and Admins change Roles.';
        }
        if (\in_array('role', $changed, true) && \in_array(Role::Owner, [$stored['role'], $changes['role']], true)) {
            if (Role::Owner !== $actor->getRole()) {
                return self::OWNERS_ONLY;
            }
            if (Role::Owner === $stored['role'] && $member->isActive() && 1 === $this->owners($member)) {
                return 'The Workspace needs at least one Owner.';
            }
        }
        if (\in_array('team', $changed, true) && !$this->manages($actor)) {
            return 'Only Owners and Admins assign Teams.';
        }

        return null;
    }

    /** How many Owners who can sign in the Member's Workspace has; one who is invited can't yet. */
    private function owners(Member $member): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(m.id) FROM '.Member::class.' m WHERE m.workspace = :ws AND m.role = :owner AND m.status = :active')
            ->setParameters(['ws' => $member->getWorkspace(), 'owner' => Role::Owner, 'active' => MemberStatus::Active])
            ->getSingleScalarResult();
    }
}
