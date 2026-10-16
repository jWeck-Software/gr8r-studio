<?php

namespace App\Snapshot;

use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Everything a Member may see of their Workspace, in the frontend's shape:
 * `data` is the frontend's S.data, `prefs` its S.prefs and `views` its S.views.
 *
 * Visibility rules and per-Member mapping live here, so callers only say who is asking.
 */
final readonly class Snapshot
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{data: array<string, mixed>, prefs: object, views: object} */
    public function of(Member $member): array
    {
        $ws = $member->getWorkspace();
        $byWorkspace = ['workspace' => $ws];

        $members = $this->em->getRepository(Member::class)->findBy($byWorkspace, ['id' => 'ASC']);
        $projects = $this->em->getRepository(Project::class)->findBy($byWorkspace);
        $tasks = $this->em->getRepository(Task::class)->findBy($byWorkspace);

        return [
            'data' => [
                'ws' => ['name' => $ws->getName(), 'url' => $ws->getUrl(), 'c' => $ws->getC(), 'brand' => $ws->isBrand()],
                'me' => $member->getId(),
                'members' => array_map(static fn (Member $m): array => $m->toRecord(), $members),
                'projects' => self::inCreationOrder(array_map(static fn (Project $p): array => $p->toRecord(), $projects)),
                'tasks' => self::inCreationOrder(array_map(static fn (Task $t): array => $t->toRecord(), $tasks)),
                'projOrder' => $ws->getProjOrder(),
                'comments' => [],
                'activity' => [],
                'notifs' => [],
                'files' => [],
                'events' => [],
                'savedViews' => [],
                'teams' => [],
                'recentSearches' => [],
                'notifPrefs' => new \stdClass(),
                ...$this->demoOnly(),
            ],
            'prefs' => new \stdClass(),
            'views' => new \stdClass(),
        ];
    }

    /**
     * Records sorted by id with numeric parts compared as numbers (t2 before t10), which keeps
     * seeded records in their original order; the frontend sorts for display itself.
     *
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    private static function inCreationOrder(array $records): array
    {
        usort($records, static fn (array $a, array $b): int => strnatcmp($a['id'], $b['id']));

        return $records;
    }

    /**
     * Account features that stay demo data in the UI (workspace switcher, sessions, billing,
     * two-factor auth). They are never synced.
     *
     * @return array<string, mixed>
     */
    private function demoOnly(): array
    {
        $today = $this->clock->now();
        $dOff = static fn (int $days): string => $today->modify(\sprintf('%+d days', $days))->format('Y-m-d');

        return [
            'workspaces' => [
                ['id' => 'w1', 'name' => 'Gr8r Studio', 'c' => '#1D1C1A', 'plan' => 'Team', 'brand' => true],
                ['id' => 'w2', 'name' => 'Personal', 'c' => '#3D8E5F', 'plan' => 'Free'],
                ['id' => 'w3', 'name' => 'Acme Labs', 'c' => '#3B82C4', 'plan' => 'Business'],
            ],
            'sessions' => [
                ['id' => 'se1', 'dev' => 'MacBook Pro · Chrome', 'loc' => 'San Francisco, US', 'at' => 'Active now', 'cur' => true],
                ['id' => 'se2', 'dev' => 'iPhone 15 · Gr8r app', 'loc' => 'San Francisco, US', 'at' => '2 hours ago'],
                ['id' => 'se3', 'dev' => 'Windows · Edge', 'loc' => 'Oakland, US', 'at' => 'Sep 12'],
            ],
            'invoices' => [
                ['id' => 'INV-2026-009', 'date' => $dOff(-23), 'amt' => '$96.00', 'st' => 'Paid'],
                ['id' => 'INV-2026-008', 'date' => $dOff(-53), 'amt' => '$96.00', 'st' => 'Paid'],
                ['id' => 'INV-2026-007', 'date' => $dOff(-84), 'amt' => '$80.00', 'st' => 'Paid'],
                ['id' => 'INV-2026-006', 'date' => $dOff(-114), 'amt' => '$80.00', 'st' => 'Paid'],
            ],
            'tfa' => false,
        ];
    }
}
