<?php

namespace App\Snapshot;

use App\Access\ProjectAccess;
use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Member;
use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskKeys;
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
        $visibleTasks = $this->em->getRepository(Task::class)->findBy([...$byWorkspace, 'project' => $visible]);
        $tasks = self::withFavorites($member, 'tasks', array_map(static fn (Task $t): array => $t->toRecord(), $visibleTasks));
        $seqs = self::keySequences($tasks);

        return [
            'data' => [
                'ws' => ['name' => $ws->getName(), 'url' => $ws->getUrl(), 'c' => $ws->getC(), 'brand' => $ws->isBrand()],
                'me' => $member->getId(),
                'members' => array_map(static fn (Member $m): array => $m->toRecord(), $members),
                'projects' => self::inCreationOrder(self::withFavorites($member, 'projects', array_map(static fn (Project $p): array => [...$p->toRecord(), 'seq' => $seqs[$p->getId()] ?? TaskKeys::FIRST_SEQ], $projects))),
                'tasks' => self::inCreationOrder($tasks),
                'projOrder' => array_values(array_intersect($ws->getProjOrder(), $visible)),
                'comments' => $this->comments($visibleTasks),
                'activity' => $this->activity($member, $visible),
                'notifs' => $this->notifs($member, $visible, array_map(static fn (Task $t): string => $t->getId(), $visibleTasks)),
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
     * The Comments on these Tasks, oldest first, as the frontend appends new ones.
     *
     * @param list<Task> $tasks
     *
     * @return list<array<string, mixed>>
     */
    private function comments(array $tasks): array
    {
        $comments = $this->em->getRepository(Comment::class)->findBy(['task' => $tasks], ['at' => 'ASC', 'id' => 'ASC']);

        return array_map(static fn (Comment $c): array => $c->toRecord(), $comments);
    }

    /**
     * The Activity about the visible Projects and about no Project at all, newest first, as the
     * frontend puts new entries first. Entries about a deleted Project are left out with it.
     *
     * @param list<string> $visibleProjects
     *
     * @return list<array<string, mixed>>
     */
    private function activity(Member $member, array $visibleProjects): array
    {
        $entries = $this->em->createQuery('SELECT a FROM '.Activity::class.' a WHERE a.workspace = :ws AND (a.project IS NULL OR a.project IN (:visible)) ORDER BY a.at DESC, a.id DESC')
            ->setParameters(['ws' => $member->getWorkspace(), 'visible' => $visibleProjects])
            ->getResult();

        return array_map(static fn (Activity $a): array => $a->toRecord(), $entries);
    }

    /**
     * The Member's own Notifications, newest first, as the frontend lists them. Those about a Task
     * or Project the Member can't see (now) are left out, a deleted one included.
     *
     * @param list<string> $visibleProjects
     * @param list<string> $visibleTasks
     *
     * @return list<array<string, mixed>>
     */
    private function notifs(Member $member, array $visibleProjects, array $visibleTasks): array
    {
        $notifications = $this->em->getRepository(Notification::class)->findBy(['recipient' => $member], ['at' => 'DESC', 'id' => 'DESC']);
        $projects = array_flip($visibleProjects);
        $tasks = array_flip($visibleTasks);
        $records = array_map(static fn (Notification $n): array => $n->toRecord(), $notifications);

        return array_values(array_filter($records, static fn (array $n): bool => (!isset($n['task']) || isset($tasks[$n['task']])) && (!isset($n['project']) || isset($projects[$n['project']]))));
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
     * Each Project's Task key sequence by Project id (TaskKeys::seq): the frontend's nextKey()
     * numbers the next Task seq + 1, so keys stay unique across reloads.
     *
     * @param list<array<string, mixed>> $tasks
     *
     * @return array<string, int>
     */
    private static function keySequences(array $tasks): array
    {
        $keys = [];
        foreach ($tasks as $t) {
            $keys[$t['project']][] = $t['key'];
        }

        return array_map(TaskKeys::seq(...), $keys);
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
     * Features that stay demo data in the UI (workspace switcher, sessions, billing,
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
