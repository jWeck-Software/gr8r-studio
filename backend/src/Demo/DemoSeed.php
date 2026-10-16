<?php

namespace App\Demo;

use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The demo Workspace "Gr8r Studio", ported from the frontend's src/data/seed.js with
 * dates relative to today. Every active Member gets a User with the demo password.
 *
 * Not yet seeded (their tickets add them): Teams, Comments, Activity, Notifications,
 * Files, Events, Saved views and the Private project "Customer Portal".
 */
final class DemoSeed
{
    public const string PASSWORD = 'gr8r-studio-demo';
    private const string WORKSPACE_URL = 'gr8rstudio';

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Replaces the demo Workspace and its Users with a fresh copy. */
    public function load(): void
    {
        $this->now = $this->clock->now();
        $this->em->wrapInTransaction(function (): void {
            $this->purge();
            $ws = new Workspace('Gr8r Studio', self::WORKSPACE_URL, '#1D1C1A', true, ['p1', 'p2', 'p3', 'p4', 'p5', 'p7']);
            $this->em->persist($ws);
            $members = $this->members($ws);
            $projects = $this->projects($ws, $members['m1']);
            $this->tasks($ws, $projects, $members);
        });
        $this->em->clear();
    }

    private function purge(): void
    {
        $this->em->createQuery('DELETE FROM '.Workspace::class.' w WHERE w.url = :url')
            ->execute(['url' => self::WORKSPACE_URL]);
        $this->em->createQuery('DELETE FROM '.User::class.' u WHERE u.email LIKE :domain')
            ->execute(['domain' => '%@gr8rstudio.com']);
    }

    /** @return array<string, Member> */
    private function members(Workspace $ws): array
    {
        $rows = [
            ['m1', 'Tanjim Islam', 'hello@gr8rstudio.com', 'Owner', 'product', 'Head of Product', '#5A67D8', 'active', 0, 'San Francisco'],
            ['m2', 'Sarah Chen', 'sarah@gr8rstudio.com', 'Admin', 'design', 'Design Lead', '#C54B78', 'active', 4, 'New York'],
            ['m3', 'John Carter', 'john@gr8rstudio.com', 'Member', 'eng', 'Frontend Engineer', '#3B82C4', 'active', 22, 'London'],
            ['m4', 'Emma Wilson', 'emma@gr8rstudio.com', 'Member', 'design', 'Product Designer', '#23918A', 'active', 9, 'Berlin'],
            ['m5', 'Priya Patel', 'priya@gr8rstudio.com', 'Member', 'mkt', 'Content Strategist', '#C48A1E', 'active', 95, 'Toronto'],
            ['m6', 'Marcus Lee', 'marcus@gr8rstudio.com', 'Member', 'eng', 'Backend Engineer', '#8662C9', 'active', 240, 'Singapore'],
            ['m7', 'Lena Fischer', 'lena@gr8rstudio.com', 'Admin', 'product', 'QA Lead', '#3D8E5F', 'active', 1500, 'Munich'],
            ['m8', 'Diego Alvarez', 'diego@freelance.io', 'Guest', 'mkt', 'Freelance Copywriter', '#C0612B', 'invited', null, 'Madrid'],
        ];
        $members = [];
        foreach ($rows as [$id, $name, $email, $role, $team, $title, $c, $status, $last, $tz]) {
            $user = null;
            if ('active' === $status) {
                $user = new User($email, $name);
                $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
                $this->em->persist($user);
            }
            $members[$id] = new Member($id, $ws, $name, $email, $role, $team, $title, $c, $status, $last, $tz, $user);
            $this->em->persist($members[$id]);
        }

        return $members;
    }

    /**
     * `fav` in the seeded records is the star of the signed-in demo Member, the Owner m1.
     *
     * @return array<string, Project>
     */
    private function projects(Workspace $ws, Member $owner): array
    {
        $records = [
            [
                'id' => 'p1', 'key' => 'WEB', 'name' => 'Website Redesign', 'icon' => 'globe', 'color' => 'indigo', 'status' => 'active',
                'team' => 'design', 'lead' => 'm2', 'due' => $this->dOff(24), 'start' => $this->dOff(-30), 'fav' => true,
                'members' => ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7'],
                'desc' => 'Rebuild gr8rstudio.com with a clearer information architecture, a responsive component library, and a faster CMS-driven blog.',
                'milestones' => [
                    ['name' => 'Wireframes signed off', 'date' => $this->dOff(2)],
                    ['name' => 'Dev handoff', 'date' => $this->dOff(14)],
                    ['name' => 'Public launch', 'date' => $this->dOff(24)],
                ],
                'last' => 12,
            ],
            [
                'id' => 'p2', 'key' => 'MOB', 'name' => 'Mobile App', 'icon' => 'smartphone', 'color' => 'blue', 'status' => 'active',
                'team' => 'eng', 'lead' => 'm3', 'due' => $this->dOff(52), 'start' => $this->dOff(-45), 'fav' => true,
                'members' => ['m1', 'm3', 'm4', 'm6', 'm7'],
                'desc' => 'Native iOS and Android client for field teams with offline sync, push notifications, and biometric sign-in.',
                'milestones' => [
                    ['name' => 'Beta build', 'date' => $this->dOff(9)],
                    ['name' => 'App Store submission', 'date' => $this->dOff(45)],
                ],
                'last' => 48,
            ],
            [
                'id' => 'p3', 'key' => 'MKT', 'name' => 'Marketing Campaign', 'icon' => 'megaphone', 'color' => 'rose', 'status' => 'planning',
                'team' => 'mkt', 'lead' => 'm5', 'due' => $this->dOff(40), 'start' => $this->dOff(-10), 'fav' => false,
                'members' => ['m1', 'm4', 'm5', 'm7', 'm8'],
                'desc' => 'Q4 awareness campaign across paid social, email, and a launch webinar targeting operations leads.',
                'milestones' => [
                    ['name' => 'Creative lock', 'date' => $this->dOff(12)],
                    ['name' => 'Campaign live', 'date' => $this->dOff(21)],
                ],
                'last' => 130,
            ],
            [
                'id' => 'p4', 'key' => 'LCH', 'name' => 'Product Launch', 'icon' => 'rocket', 'color' => 'amber', 'status' => 'risk',
                'team' => 'product', 'lead' => 'm1', 'due' => $this->dOff(12), 'start' => $this->dOff(-21), 'fav' => false,
                'members' => ['m1', 'm2', 'm3', 'm5', 'm7'],
                'desc' => 'Coordinate the Workflows 2.0 launch: pricing, docs, press, and sales enablement across teams.',
                'milestones' => [
                    ['name' => 'Go / no-go', 'date' => $this->dOff(11)],
                    ['name' => 'Launch day', 'date' => $this->dOff(12)],
                ],
                'last' => 35,
            ],
            [
                'id' => 'p5', 'key' => 'DS', 'name' => 'Design System', 'icon' => 'component', 'color' => 'violet', 'status' => 'active',
                'team' => 'design', 'lead' => 'm2', 'due' => $this->dOff(70), 'start' => $this->dOff(-60), 'fav' => false,
                'members' => ['m2', 'm3', 'm4'],
                'desc' => 'Shared tokens, components, and documentation used by web and mobile teams.',
                'milestones' => [['name' => 'v2 tokens', 'date' => $this->dOff(3)]],
                'last' => 300,
            ],
            [
                'id' => 'p7', 'key' => 'MW', 'name' => 'Marketing Website', 'icon' => 'layout-grid', 'color' => 'green', 'status' => 'complete',
                'team' => 'mkt', 'lead' => 'm5', 'due' => $this->dOff(-18), 'start' => $this->dOff(-80), 'fav' => false,
                'members' => ['m1', 'm3', 'm5'],
                'desc' => 'Launch site for the spring release. Shipped and handed to the web team for maintenance.',
                'milestones' => [],
                'last' => 26000,
            ],
        ];
        $projects = [];
        foreach ($records as $record) {
            $project = new Project($record['id'], $ws);
            $project->update($record);
            $this->em->persist($project);
            $owner->star('projects', $record['id'], $record['fav']);
            $projects[$record['id']] = $project;
        }

        return $projects;
    }

    /**
     * @param array<string, Project> $projects
     * @param array<string, Member>  $members
     */
    private function tasks(Workspace $ws, array $projects, array $members): void
    {
        $tasks = [];
        $t = function (string $p, string $title, string $status, string $a, string $prio, ?int $due, array $labels = [], array $x = []) use (&$tasks, $projects): void {
            $n = \count($tasks) + 1;
            $count = \count(array_filter($tasks, static fn (array $q): bool => $q['project'] === $p)) + 1;
            $key = $projects[$p]->toRecord()['key'];
            $len = $x['len'] ?? 4;
            $tasks[] = array_merge([
                'id' => 't'.$n,
                'key' => $key.'-'.(100 + $count * 3),
                'project' => $p,
                'title' => $title,
                'status' => $status,
                'assignee' => $a,
                'priority' => $prio,
                'due' => null === $due ? null : $this->dOff($due),
                'start' => null === $due ? null : $this->dOff($due - $len),
                'labels' => $labels,
                'subtasks' => [],
                'attachments' => [],
                'deps' => [],
                'desc' => '',
                'estimate' => $x['est'] ?? null,
                'created' => $this->minsAgo(60 * 24 * ($x['age'] ?? 12)),
                'updated' => $this->minsAgo(60 * ($x['upd'] ?? 30)),
                'order' => $n,
                'fav' => false,
                'recur' => null,
            ], array_diff_key($x, ['est' => 0, 'len' => 0, 'age' => 0, 'upd' => 0]));
        };

        // Website Redesign
        $t('p1', 'Audit existing navigation', 'done', 'm2', 'high', -12, ['research'], ['est' => '2d', 'len' => 5, 'age' => 28]);
        $t('p1', 'Summarize stakeholder interviews', 'done', 'm1', 'medium', -9, ['research'], ['est' => '1d', 'age' => 26]);
        $t('p1', 'Create homepage wireframes', 'review', 'm2', 'high', 1, ['design'], [
            'est' => '3d',
            'len' => 7,
            'fav' => true,
            'desc' => '<p>Low-fidelity wireframes for the new homepage covering <b>desktop, tablet, and mobile</b> breakpoints.</p><ul><li>Hero with product value proposition</li><li>Customer logos and proof points</li><li>Feature overview linking to product pages</li></ul><p>Reference the navigation audit for IA decisions.</p>',
            'subtasks' => [
                ['id' => 's1', 'title' => 'Desktop layout', 'done' => true],
                ['id' => 's2', 'title' => 'Tablet layout', 'done' => true],
                ['id' => 's3', 'title' => 'Mobile layout', 'done' => true],
                ['id' => 's4', 'title' => 'Annotate interactions', 'done' => false],
            ],
            'attachments' => [
                ['id' => 'a1', 'name' => 'homepage-wireframes-v3.fig', 'type' => 'fig', 'size' => '4.2 MB', 'by' => 'm2', 'at' => $this->minsAgo(300)],
                ['id' => 'a2', 'name' => 'nav-audit-findings.pdf', 'type' => 'pdf', 'size' => '860 KB', 'by' => 'm2', 'at' => $this->minsAgo(8000)],
            ],
        ]);
        $t('p1', 'Finalize navigation', 'progress', 'm3', 'high', 0, ['design', 'frontend'], [
            'est' => '2d',
            'len' => 4,
            'subtasks' => [
                ['id' => 's5', 'title' => 'Primary nav structure', 'done' => true],
                ['id' => 's6', 'title' => 'Mega-menu content', 'done' => false],
                ['id' => 's7', 'title' => 'Mobile drawer behavior', 'done' => false],
            ],
        ]);
        $t('p1', 'Design responsive navigation', 'progress', 'm4', 'medium', 3, ['design'], [
            'est' => '3d',
            'len' => 6,
            'subtasks' => [
                ['id' => 's8', 'title' => 'Breakpoint rules', 'done' => true],
                ['id' => 's9', 'title' => 'Sticky header states', 'done' => false],
            ],
        ]);
        $t('p1', 'Prepare design system tokens', 'todo', 'm1', 'medium', 6, ['design'], ['est' => '2d', 'len' => 4]);
        $t('p1', 'Review responsive layouts', 'todo', 'm4', 'medium', 8, ['qa', 'design'], ['est' => '1d', 'len' => 3]);
        $t('p1', 'Prepare developer handoff', 'todo', 'm2', 'high', 14, ['frontend'], ['est' => '2d', 'len' => 4, 'deps' => ['t3', 't5']]);
        $t('p1', 'Design mobile onboarding', 'progress', 'm4', 'urgent', -1, ['design'], [
            'est' => '3d',
            'len' => 6,
            'subtasks' => [
                ['id' => 's10', 'title' => 'Welcome screens', 'done' => true],
                ['id' => 's11', 'title' => 'Permission prompts', 'done' => false],
                ['id' => 's12', 'title' => 'Empty states', 'done' => false],
            ],
        ]);
        $t('p1', 'Write homepage copy', 'backlog', 'm5', 'low', 18, ['content'], ['est' => '2d']);
        $t('p1', 'Set up analytics events', 'backlog', 'm6', 'medium', 20, ['backend', 'growth'], ['est' => '1d']);
        $t('p1', 'Accessibility audit of templates', 'backlog', 'm7', 'high', 22, ['qa'], ['est' => '2d']);
        $t('p1', 'Build hero component', 'todo', 'm3', 'medium', 10, ['frontend'], ['est' => '2d', 'len' => 3, 'deps' => ['t3']]);
        $t('p1', 'Image optimization pipeline', 'review', 'm6', 'low', 2, ['backend'], ['est' => '1d', 'len' => 5]);
        $t('p1', 'Weekly design sync notes', 'todo', 'm1', 'low', 2, ['content'], ['recur' => 'Weekly', 'est' => '30m', 'len' => 0]);
        $t('p1', 'Migrate blog to new CMS', 'backlog', 'm3', 'medium', 28, ['backend'], ['est' => '5d', 'len' => 8]);
        $t('p1', 'Footer and legal pages', 'done', 'm5', 'low', -5, ['frontend'], ['est' => '1d']);
        $t('p1', 'Fix broken anchor links on pricing', 'done', 'm3', 'medium', -3, ['bug'], ['est' => '2h', 'len' => 1]);
        // Mobile App
        $t('p2', 'Onboarding flow prototype', 'progress', 'm4', 'high', 4, ['design'], ['est' => '3d', 'len' => 6]);
        $t('p2', 'Push notification service', 'progress', 'm6', 'high', 6, ['backend'], ['est' => '5d', 'len' => 9]);
        $t('p2', 'Offline mode sync', 'todo', 'm3', 'urgent', 5, ['frontend', 'backend'], ['est' => '8d', 'len' => 10, 'deps' => ['t20']]);
        $t('p2', 'App Store screenshots', 'backlog', 'm5', 'low', 40, ['content'], ['est' => '1d']);
        $t('p2', 'Crash reporting setup', 'done', 'm6', 'medium', -8, ['backend'], ['est' => '1d']);
        $t('p2', 'Biometric sign-in', 'review', 'm3', 'high', 2, ['frontend'], ['est' => '3d', 'len' => 6]);
        $t('p2', 'Settings screen redesign', 'todo', 'm4', 'medium', 9, ['design'], ['est' => '2d']);
        $t('p2', 'Triage beta tester feedback', 'todo', 'm1', 'medium', 0, ['research'], ['est' => '4h', 'len' => 1, 'recur' => 'Weekly']);
        $t('p2', 'Crash on Android 12 when rotating', 'todo', 'm3', 'urgent', 1, ['bug'], ['est' => '1d', 'len' => 1]);
        // Marketing Campaign
        $t('p3', 'Campaign brief', 'done', 'm5', 'medium', -6, ['content'], ['est' => '1d']);
        $t('p3', 'Landing page copy', 'progress', 'm5', 'medium', 4, ['content'], ['est' => '2d']);
        $t('p3', 'Paid social creatives', 'todo', 'm4', 'high', 7, ['design', 'growth'], ['est' => '3d', 'len' => 5]);
        $t('p3', 'Email nurture sequence', 'todo', 'm8', 'medium', 9, ['content'], ['est' => '2d']);
        $t('p3', 'Influencer outreach list', 'backlog', 'm7', 'low', null, ['growth']);
        $t('p3', 'Launch webinar deck', 'todo', 'm1', 'medium', 15, ['content'], ['est' => '2d']);
        $t('p3', 'Budget approval', 'review', 'm1', 'urgent', -2, [], ['est' => '1h', 'len' => 1]);
        // Product Launch
        $t('p4', 'Launch readiness checklist', 'progress', 'm1', 'urgent', 3, ['qa'], [
            'est' => '1d',
            'len' => 10,
            'subtasks' => [
                ['id' => 's20', 'title' => 'Docs published', 'done' => true],
                ['id' => 's21', 'title' => 'Pricing live', 'done' => false],
                ['id' => 's22', 'title' => 'Support trained', 'done' => false],
                ['id' => 's23', 'title' => 'Status page updated', 'done' => true],
            ],
        ]);
        $t('p4', 'Pricing page update', 'todo', 'm2', 'high', 6, ['design', 'frontend'], ['est' => '2d']);
        $t('p4', 'Press release draft', 'review', 'm5', 'medium', 1, ['content'], ['est' => '1d']);
        $t('p4', 'Sales enablement docs', 'todo', 'm7', 'medium', 8, ['content'], ['est' => '2d']);
        $t('p4', 'Release notes', 'backlog', 'm3', 'low', 11, ['content'], ['est' => '4h']);
        $t('p4', 'Go / no-go meeting', 'todo', 'm1', 'high', 11, [], ['est' => '1h', 'len' => 0]);
        // Design System
        $t('p5', 'Color tokens v2', 'done', 'm2', 'high', -4, ['design'], ['est' => '3d']);
        $t('p5', 'Button component audit', 'progress', 'm2', 'medium', 5, ['design', 'frontend'], ['est' => '2d']);
        $t('p5', 'Form field states', 'todo', 'm4', 'medium', 12, ['design'], ['est' => '3d']);
        $t('p5', 'Icon library cleanup', 'backlog', 'm4', 'low', null, ['design']);
        $t('p5', 'Documentation site', 'todo', 'm3', 'medium', 20, ['frontend'], ['est' => '5d', 'len' => 8]);
        $t('p5', 'Dark mode palette', 'review', 'm2', 'high', 2, ['design'], ['est' => '2d']);
        // Marketing Website (complete)
        $t('p7', 'Launch site QA', 'done', 'm3', 'high', -20, ['qa']);
        $t('p7', 'Spring release hero video', 'done', 'm5', 'medium', -24, ['content']);

        $i = 0;
        foreach ($tasks as &$task) {
            if ('done' === $task['status']) {
                $task['completedAt'] = $this->minsAgo(60 * (6 + $i++ * 17));
            }
        }
        unset($task);
        $tasks[2]['updated'] = $this->minsAgo(38);

        foreach ($tasks as $record) {
            $task = new Task($record['id'], $ws);
            $task->update($record, $projects[$record['project']], $members[$record['assignee']]);
            $this->em->persist($task);
            $members['m1']->star('tasks', $record['id'], $record['fav']);
        }
    }

    /** A calendar date $days from today, as YYYY-MM-DD. */
    private function dOff(int $days): string
    {
        return $this->now->modify(\sprintf('%+d days', $days))->format('Y-m-d');
    }

    /** Epoch milliseconds $minutes before now. */
    private function minsAgo(int $minutes): int
    {
        return (int) $this->now->format('Uv') - $minutes * 60_000;
    }
}
