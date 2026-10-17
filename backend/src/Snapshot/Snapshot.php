<?php

namespace App\Snapshot;

use App\Access\ProjectAccess;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Everything a Member may see of their Workspace, in the frontend's shape:
 * `data` is the frontend's S.data, `prefs` its S.prefs and `views` its S.views.
 *
 * Visibility (ProjectAccess) and per-Member mapping such as favorites are applied here, so
 * callers only say who is asking.
 */
final readonly class Snapshot
{
    /** The frontend numbers a Project's first new Task 201, after the seeded keys. */
    private const int FIRST_SEQ = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private ProjectAccess $access,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{data: array<string, mixed>, prefs: object, views: object} */
    public function of(Member $member): array
    {
        $ws = $member->getWorkspace();
        $byWorkspace = ['workspace' => $ws];

        $members = $this->em->getRepository(Member::class)->findBy($byWorkspace, ['id' => 'ASC']);
        $projects = $this->access->visibleProjects($member);
        $visible = array_map(static fn (Project $p): string => $p->getId(), $projects);
        $tasks = self::withFavorites($member, 'tasks', array_map(static fn (Task $t): array => $t->toRecord(), $this->em->getRepository(Task::class)->findBy([...$byWorkspace, 'project' => $visible])));
        $seqs = self::keySequences($tasks);

        return [
            'data' => [
                'ws' => ['name' => $ws->getName(), 'url' => $ws->getUrl(), 'c' => $ws->getC(), 'brand' => $ws->isBrand()],
                'me' => $member->getId(),
                'members' => array_map(static fn (Member $m): array => $m->toRecord(), $members),
                'projects' => self::inCreationOrder(self::withFavorites($member, 'projects', array_map(static fn (Project $p): array => [...$p->toRecord(), 'seq' => $seqs[$p->getId()] ?? self::FIRST_SEQ], $projects))),
                'tasks' => self::inCreationOrder($tasks),
                'projOrder' => array_values(array_intersect($ws->getProjOrder(), $visible)),
                'comments' => [],
                'activity' => [],
                'notifs' => [],
                'files' => [],
                'events' => [],
                'savedViews' => [],
                // No `teams` key until Teams are stored: the frontend then uses its built-in Teams.
                'recentSearches' => [],
                'notifPrefs' => new \stdClass(),
                ...$this->demoOnly(),
            ],
            'prefs' => new \stdClass(),
            'views' => new \stdClass(),
        ];
    }

    /**
     * Sets `fav` on each record: whether this Member starred it. Favorites are per Member.
     *
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    private static function withFavorites(Member $member, string $collection, array $records): array
    {
        $starred = array_flip($member->starred($collection));

        return array_map(static fn (array $r): array => [...$r, 'fav' => isset($starred[$r['id']])], $records);
    }

    /**
     * The highest Task key number per Project id, archived Tasks included, and at least
     * FIRST_SEQ. The frontend's nextKey() numbers the next Task seq + 1, so keys stay unique
     * across reloads (Task keys are client-derived, ADR 0003).
     *
     * @param list<array<string, mixed>> $tasks
     *
     * @return array<string, int>
     */
    private static function keySequences(array $tasks): array
    {
        $seqs = [];
        foreach ($tasks as $t) {
            $number = (int) substr($t['key'], strrpos($t['key'], '-') + 1);
            $seqs[$t['project']] = max($seqs[$t['project']] ?? self::FIRST_SEQ, $number);
        }

        return $seqs;
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
