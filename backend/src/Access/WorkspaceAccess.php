<?php

namespace App\Access;

use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Who may change the Workspace's people and settings: Members' Roles and Teams, Invites, removing
 * Members, Teams, and the Workspace's name and colour. Owners and Admins manage them; changing an
 * Owner's Role is the Owners' own business, and a Workspace always keeps one Owner. Everyone edits
 * their own name, title and avatar colour.
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
        return \in_array($member->getRole(), ['Owner', 'Admin'], true);
    }

    /** Why the actor may not invite someone into the Workspace with this Role, or null when they may. */
    public function whyNotInvite(Member $actor, string $role): ?string
    {
        if (!$this->manages($actor)) {
            return 'Only Owners and Admins invite people.';
        }

        return 'Owner' === $role && 'Owner' !== $actor->getRole() ? self::OWNERS_ONLY : null;
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
            'Owner' === $member->getRole() && 'Owner' !== $actor->getRole() => 'Only an Owner removes an Owner.',
            default => null,
        };
    }

    /**
     * Why the actor may not change these fields of the Member, or null when they may.
     *
     * @param array{name: string, title: string, c: string, role: string, team: string} $changes
     */
    public function whyNotChange(Member $actor, Member $member, array $changes): ?string
    {
        $stored = $member->toRecord();
        $changed = array_keys(array_filter($changes, static fn (string $value, string $field): bool => $stored[$field] !== $value, \ARRAY_FILTER_USE_BOTH));
        if ([] !== array_intersect($changed, ['name', 'title', 'c']) && $actor !== $member) {
            return 'Only the Member themselves changes their name, title and avatar colour.';
        }
        if (\in_array('role', $changed, true) && !$this->manages($actor)) {
            return 'Only Owners and Admins change Roles.';
        }
        if (\in_array('role', $changed, true) && \in_array('Owner', [$stored['role'], $changes['role']], true)) {
            if ('Owner' !== $actor->getRole()) {
                return self::OWNERS_ONLY;
            }
            if ('Owner' === $stored['role'] && 1 === $this->owners($member)) {
                return 'The Workspace needs at least one Owner.';
            }
        }
        if (\in_array('team', $changed, true) && !$this->manages($actor)) {
            return 'Only Owners and Admins assign Teams.';
        }

        return null;
    }

    /** How many Owners the Member's Workspace has. */
    private function owners(Member $member): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(m.id) FROM '.Member::class.' m WHERE m.workspace = :ws AND m.role = :owner')
            ->setParameters(['ws' => $member->getWorkspace(), 'owner' => 'Owner'])
            ->getSingleScalarResult();
    }
}
