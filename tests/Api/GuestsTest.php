<?php

namespace App\Tests\Api;

use App\Entity\Member;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Gale, a Guest the Owner added to Marketing Campaign (p3), reads it and changes nothing but her stars. */
final class GuestsTest extends ApiTestCase
{
    private const string GUEST_EMAIL = 'gale@guest.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->arrangeGuest();
        $this->signIn();
        $p3 = $this->record('projects', 'p3');
        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p3', 'data' => [...$p3, 'members' => [...$p3['members'], 'mg1']]]]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/logout');
        $this->signIn(self::GUEST_EMAIL, 'guest-password');
    }

    public function testAGuestSeesOnlyTheProjectsTheyBelongTo(): void
    {
        $data = $this->getJson('/api/bootstrap')['data'];

        self::assertSame(['p3'], array_column($data['projects'], 'id'));
        self::assertSame(['p3'], array_values(array_unique(array_column($data['tasks'], 'project'))));
        self::assertCount(7, $data['tasks']);
        self::assertSame(['p3'], $data['projOrder']);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function writes(): iterable
    {
        yield 'edit a Project' => ['put', 'projects', 'You may not change the Project "p3".'];
        yield 'create a Project' => ['create', 'projects', 'You may not change the Project "pq7x2".'];
        yield 'delete a Project' => ['delete', 'projects', 'You may not change the Project "p3".'];
        yield 'edit a Task' => ['put', 'tasks', 'You may not change the Tasks of the Project "p3".'];
        yield 'create a Task' => ['create', 'tasks', 'You may not change the Tasks of the Project "p3".'];
        yield 'delete a Task' => ['delete', 'tasks', 'You may not change the Tasks of the Project "p3".'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writes')]
    public function testAGuestCannotWriteProjectsOrTasks(string $write, string $type, string $message): void
    {
        $id = 'projects' === $type ? 'p3' : 't28';
        $op = match ($write) {
            'put' => ['op' => 'put', 'type' => $type, 'id' => $id, 'data' => [...$this->record($type, $id), 'title' => 'Changed', 'name' => 'Changed']],
            'create' => ['op' => 'put', 'type' => $type, 'id' => 'projects' === $type ? 'pq7x2' : 'xk3j9q2', 'data' => [...$this->record($type, $id), 'id' => 'projects' === $type ? 'pq7x2' : 'xk3j9q2', 'key' => 'projects' === $type ? 'GST' : 'MKT-201']],
            'delete' => ['op' => 'delete', 'type' => $type, 'id' => $id],
        };

        $body = $this->sync([$op]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
    }

    public function testAGuestCannotReorderTheSidebar(): void
    {
        $body = $this->sync([['op' => 'set', 'type' => 'projOrder', 'data' => ['p3']]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not change the Project order.'], $body);
    }

    public function testAGuestStarsProjectsAndTasks(): void
    {
        $this->sync([
            ['op' => 'put', 'type' => 'projects', 'id' => 'p3', 'data' => [...$this->record('projects', 'p3'), 'fav' => true, 'seq' => 201]],
            ['op' => 'put', 'type' => 'tasks', 'id' => 't28', 'data' => [...$this->record('tasks', 't28'), 'fav' => true]],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([true, true], [$this->record('projects', 'p3')['fav'], $this->record('tasks', 't28')['fav']]);
    }

    public function testAGuestsStarCannotCarryOtherChanges(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't28', 'data' => [...$this->record('tasks', 't28'), 'fav' => true, 'title' => 'Campaign brief v2']]]);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->record('tasks', 't28')['fav']);
    }

    public function testAGuestCommentsAndReactsInTheirProject(): void
    {
        $c8 = array_column($this->getJson('/api/bootstrap')['data']['comments'], null, 'id')['c8'];

        $this->sync([
            ['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => ['id' => 'cx7k2m9', 'task' => 't34', 'by' => 'mg1', 'at' => 1792238400000, 'text' => 'Channel split attached.', 're' => []]],
            ['op' => 'put', 'type' => 'comments', 'id' => 'c8', 'data' => [...$c8, 're' => ['👀' => ['mg1']]]],
        ]);

        self::assertResponseStatusCodeSame(200);
        $comments = array_column($this->getJson('/api/bootstrap')['data']['comments'], null, 'id');
        self::assertSame(['mg1', 'Channel split attached.'], [$comments['cx7k2m9']['by'], $comments['cx7k2m9']['text']]);
        self::assertSame(['👀' => ['mg1']], $comments['c8']['re']);
    }

    /** postComment() logs Activity, and logAct() bumps the Task's `updated`: the batch carries all three. */
    public function testAGuestCommentsAsTheFrontendSendsIt(): void
    {
        $t34 = $this->record('tasks', 't34');

        $body = $this->sync([
            ['op' => 'put', 'type' => 'tasks', 'id' => 't34', 'data' => [...$t34, 'updated' => 1792238400000]],
            ['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => ['id' => 'cx7k2m9', 'task' => 't34', 'by' => 'mg1', 'at' => 1792238400000, 'text' => 'Channel split attached.', 're' => []]],
            ['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => ['id' => 'ax7k2m9', 'by' => 'mg1', 'verb' => 'commented on', 'task' => 't34', 'project' => 'p3', 'at' => 1792238400000, 'extra' => '']],
        ]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(1792238400000, $this->record('tasks', 't34')['updated']);
        self::assertSame('Channel split attached.', $this->record('comments', 'cx7k2m9')['text']);
        self::assertSame('mg1', $this->record('activity', 'ax7k2m9')['by']);
    }

    public function testAGuestCannotCommentOutsideTheirProjects(): void
    {
        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => ['id' => 'cx7k2m9', 'task' => 't3', 'by' => 'mg1', 'at' => 1792238400000, 'text' => 'Hi', 're' => []]]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not comment on the Task "t3".'], $body);
    }

    public function testAGuestSeesOnlyTheCommentsAndActivityOfTheirProjects(): void
    {
        $this->sync([['op' => 'put', 'type' => 'activity', 'id' => 'ax7k2m9', 'data' => ['id' => 'ax7k2m9', 'by' => 'mg1', 'verb' => 'signed in', 'task' => null, 'project' => null, 'at' => 1792238400000, 'extra' => '']]]);
        self::assertResponseStatusCodeSame(200);

        $data = $this->getJson('/api/bootstrap')['data'];

        self::assertSame(['c8'], array_column($data['comments'], 'id'));
        self::assertSame(['ax7k2m9', 'a6'], array_column($data['activity'], 'id'));
    }

    public function testAGuestEditsTheirOwnProfile(): void
    {
        $mg1 = $this->record('members', 'mg1');

        $body = $this->sync([['op' => 'put', 'type' => 'members', 'id' => 'mg1', 'data' => [...$mg1, 'name' => 'Gale Gray', 'title' => 'Illustrator', 'c' => '#23918A']]]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(['Gale Gray', 'Illustrator', '#23918A'], array_values(array_intersect_key($this->record('members', 'mg1'), ['name' => 0, 'title' => 0, 'c' => 0])));
    }

    /** @return iterable<string, array{string, string}> */
    public static function peopleAndSettings(): iterable
    {
        yield 'make herself a Member' => ['own role', 'Only Owners and Admins change Roles.'];
        yield 'join a Team' => ['own team', 'Only Owners and Admins assign Teams.'];
        yield 'invite someone' => ['invite', 'Only Owners and Admins invite people.'];
        yield 'remove a Member' => ['remove', 'Only Owners and Admins remove Members.'];
        yield 'create a Team' => ['team', 'Only Owners and Admins manage Teams.'];
        yield 'rename the Workspace' => ['ws', 'Only Owners and Admins change the Workspace\'s settings.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peopleAndSettings')]
    public function testAGuestManagesNoPeopleTeamsOrSettings(string $write, string $message): void
    {
        $mg1 = $this->record('members', 'mg1');
        $op = match ($write) {
            'own role' => ['op' => 'put', 'type' => 'members', 'id' => 'mg1', 'data' => [...$mg1, 'role' => 'Member']],
            'own team' => ['op' => 'put', 'type' => 'members', 'id' => 'mg1', 'data' => [...$mg1, 'team' => 'design']],
            'invite' => ['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [...$mg1, 'id' => 'mq7x2', 'email' => 'friend@guest.test', 'status' => 'invited']],
            'remove' => ['op' => 'delete', 'type' => 'members', 'id' => 'm5'],
            'team' => ['op' => 'put', 'type' => 'teams', 'id' => 'tmq7x2', 'data' => ['id' => 'tmq7x2', 'name' => 'Guests', 'icon' => 'users', 'c' => '#6B7280', 'desc' => '']],
            'ws' => ['op' => 'set', 'type' => 'ws', 'data' => ['name' => 'Gale & Co', 'url' => 'gale', 'c' => '#6B7280', 'brand' => false]],
        };

        $body = $this->sync([$op]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function calendarAndSavedViewWrites(): iterable
    {
        $event = ['id' => 'e5', 'title' => 'Campaign kickoff, moved', 'date' => '2026-10-20', 'time' => '11:00', 'project' => 'p3'];
        $view = ['id' => 'vq7x2', 'project' => 'p3', 'name' => 'Mine', 'type' => 'list', 'filters' => []];
        yield 'move an Event' => [['op' => 'put', 'type' => 'events', 'id' => 'e5', 'data' => $event], 'You may not change the Events of the Project "p3".'];
        yield 'add an Event' => [['op' => 'put', 'type' => 'events', 'id' => 'eq7x2', 'data' => [...$event, 'id' => 'eq7x2']], 'You may not change the Events of the Project "p3".'];
        yield 'delete an Event' => [['op' => 'delete', 'type' => 'events', 'id' => 'e5'], 'You may not change the Events of the Project "p3".'];
        yield 'save a view' => [['op' => 'put', 'type' => 'savedViews', 'id' => 'vq7x2', 'data' => $view], 'You may not change the Saved views of the Project "p3".'];
    }

    /** @param array<string, mixed> $op */
    #[\PHPUnit\Framework\Attributes\DataProvider('calendarAndSavedViewWrites')]
    public function testAGuestChangesNoEventsOrSavedViews(array $op, string $message): void
    {
        $body = $this->sync([$op]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => $message], $body);
        self::assertSame(['e5'], array_column($this->getJson('/api/bootstrap')['data']['events'], 'id'));
    }

    public function testAGuestKeepsTheirOwnPreferencesViewsAndSettings(): void
    {
        $views = ['p:p3' => ['filters' => [], 'sort' => ['f' => 'due', 'dir' => 1], 'group' => 'status', 'q' => '', 'hidden' => [], 'colW' => [], 'showDone' => true]];

        $body = $this->sync([
            ['op' => 'set', 'type' => 'prefs', 'data' => ['theme' => 'dark', 'density' => 'compact']],
            ['op' => 'set', 'type' => 'views', 'data' => $views],
            ['op' => 'set', 'type' => 'notifPrefs', 'data' => ['email_digest' => false]],
            ['op' => 'set', 'type' => 'recentSearches', 'data' => ['brief']],
        ]);

        self::assertSame(['ok' => true], $body);
        $bootstrap = $this->getJson('/api/bootstrap');
        self::assertRecord(['theme' => 'dark', 'density' => 'compact'], $bootstrap['prefs']);
        self::assertRecord($views, $bootstrap['views']);
        self::assertSame([['email_digest' => false], ['brief']], [$bootstrap['data']['notifPrefs'], $bootstrap['data']['recentSearches']]);
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the record as /api/bootstrap shows it */
    private function record(string $collection, string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data'][$collection], null, 'id')[$id] ?? null;
    }

    public function testAGuestUploadsNoFiles(): void
    {
        $path = $this->tempFile('notes');
        $this->client->request('POST', '/api/files', ['id' => 'fg1', 'project' => 'p3'], ['file' => new UploadedFile($path, 'notes.txt', test: true)]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'message' => 'You may not change the Files of the Project "p3".'], $this->lastJson());
        self::assertNull($this->record('files', 'fg1'));
    }

    public function testAGuestDownloadsTheFilesOfTheirProjectButRenamesAndDeletesNone(): void
    {
        $f11 = $this->record('files', 'f11');
        $this->client->request('GET', '/api/files/f11/content');
        self::assertResponseStatusCodeSame(200);

        foreach ([['op' => 'put', 'type' => 'files', 'id' => 'f11', 'data' => [...$f11, 'name' => 'mine.pdf']], ['op' => 'delete', 'type' => 'files', 'id' => 'f11']] as $op) {
            $body = $this->sync([$op]);

            self::assertResponseStatusCodeSame(403);
            self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not change the File "f11".'], $body);
        }
        self::assertSame('campaign-brief.pdf', $this->record('files', 'f11')['name']);
        self::assertSame(['f11'], array_column($this->getJson('/api/bootstrap')['data']['files'], 'id'));
    }

    private function arrangeGuest(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $ws = $em->getRepository(Workspace::class)->findOneBy(['url' => 'gr8rstudio']);
        $user = new User(self::GUEST_EMAIL, 'Gale Guest');
        $user->setPassword($hasher->hashPassword($user, 'guest-password'));
        $em->persist($user);
        $em->persist(new Member('mg1', $ws, 'Gale Guest', self::GUEST_EMAIL, 'Guest', 'mkt', 'Freelance Designer', '#C0612B', 'active', 0, 'Lisbon', $user));
        $em->flush();
        $em->clear();
    }
}
