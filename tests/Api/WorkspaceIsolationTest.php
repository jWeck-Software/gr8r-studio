<?php

namespace App\Tests\Api;

use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Role;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** A second Workspace, "Acme Labs", next to the demo one: neither sees nor touches the other. */
final class WorkspaceIsolationTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->arrangeAcmeLabs();
    }

    public function testBootstrapShowsOnlyTheMembersOwnWorkspace(): void
    {
        $this->signIn('ada@acme.test', 'acme-password');

        $data = $this->getJson('/api/bootstrap')['data'];

        self::assertSame('Acme Labs', $data['ws']['name']);
        self::assertSame('mac1', $data['me']);
        self::assertSame(['mac1'], array_column($data['members'], 'id'));
        self::assertSame(['pac1'], array_column($data['projects'], 'id'));
        self::assertSame(['tac1'], array_column($data['tasks'], 'id'));
    }

    public function testAnIdUsedInAnotherWorkspaceIsRefused(): void
    {
        $this->signIn();

        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'tasks', 'id' => 'tac1', 'data' => self::task(['id' => 'tac1'])]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The id "tac1" is already used.'], $body);
    }

    public function testDeletingAnotherWorkspacesTaskChangesNothing(): void
    {
        $this->signIn();
        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'tasks', 'id' => 'tac1']]]);
        self::assertSame(['ok' => true], $body);

        $this->client->request('POST', '/api/logout');
        $this->signIn('ada@acme.test', 'acme-password');
        self::assertSame(['tac1'], array_column($this->getJson('/api/bootstrap')['data']['tasks'], 'id'));
    }

    public function testDeletingAnotherWorkspacesFileChangesNothing(): void
    {
        $this->signIn('ada@acme.test', 'acme-password');
        $this->client->request('POST', '/api/files', ['id' => 'fac1', 'project' => 'pac1'], ['file' => new UploadedFile($this->tempFile('plans'), 'plans.txt', test: true)]);
        self::assertResponseStatusCodeSame(201);
        $this->client->request('POST', '/api/logout');
        $this->signIn();

        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'files', 'id' => 'fac1']]]);

        self::assertSame(['ok' => true], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('ada@acme.test', 'acme-password');
        self::assertSame(['fac1'], array_column($this->getJson('/api/bootstrap')['data']['files'], 'id'));
        $this->client->request('GET', '/api/files/fac1/content');
        self::assertResponseIsSuccessful();
    }

    /** Notifications are made by the server only; another Workspace's are as unknown as made-up ones. */
    public function testMarkingAnotherWorkspacesNotificationReadChangesNothing(): void
    {
        $this->signIn('ada@acme.test', 'acme-password');
        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'notifs', 'id' => 'n1', 'data' => ['id' => 'n1', 'type' => 'mention', 'by' => 'm2', 'task' => 't3', 'text' => 'mentioned you in', 'snippet' => '', 'at' => 0, 'read' => true]]]]);
        self::assertSame(['ok' => true], $body);

        $this->client->request('POST', '/api/logout');
        $this->signIn();
        self::assertFalse(array_column($this->getJson('/api/bootstrap')['data']['notifs'], null, 'id')['n1']['read']);
    }

    public function testATaskCannotBePutIntoAnotherWorkspacesProject(): void
    {
        $this->signIn();

        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::task(['project' => 'pac1'])]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The Task\'s Project "pac1" does not exist.', $body['message']);
    }

    public function testATaskCannotBeAssignedToAnotherWorkspacesMember(): void
    {
        $this->signIn();

        $body = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'tasks', 'id' => 'xk3j9q2', 'data' => self::task(['assignee' => 'mac1'])]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The Task\'s assignee "mac1" is not a Member of this Workspace.', $body['message']);
    }

    public function testCommentsStayInTheirWorkspace(): void
    {
        $this->signIn('ada@acme.test', 'acme-password');
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'comments', 'id' => 'cac1', 'data' => ['id' => 'cac1', 'task' => 'tac1', 'by' => 'mac1', 'at' => 1792238400000, 'text' => 'Beep beep', 're' => []]]]]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/logout');
        $this->signIn();
        $comment = ['id' => 'cac1', 'task' => 't4', 'by' => 'm1', 'at' => 1792238400000, 'text' => 'Mine', 're' => []];

        $reused = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'comments', 'id' => 'cac1', 'data' => $comment]]]);
        $onTheirTask = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => [...$comment, 'id' => 'cx7k2m9', 'task' => 'tac1']]]]);
        $deleted = $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'comments', 'id' => 'cac1']]]);

        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The id "cac1" is already used.'], $reused);
        self::assertSame('The Comment\'s Task "tac1" does not exist.', $onTheirTask['message']);
        self::assertSame(['ok' => true], $deleted);
        self::assertNotContains('cac1', array_column($this->getJson('/api/bootstrap')['data']['comments'], 'id'));
    }

    public function testActivityStaysInItsWorkspace(): void
    {
        $this->signIn('ada@acme.test', 'acme-password');
        $entry = ['id' => 'aac1', 'by' => 'mac1', 'verb' => 'created', 'task' => 'tac1', 'project' => 'pac1', 'at' => 1792238400000, 'extra' => ''];
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'activity', 'id' => 'aac1', 'data' => $entry]]]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/logout');
        $this->signIn();

        $reused = $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'activity', 'id' => 'aac1', 'data' => [...$entry, 'task' => 't4', 'project' => 'p1']]]]);

        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The id "aac1" is already used.'], $reused);
        self::assertNotContains('aac1', array_column($this->getJson('/api/bootstrap')['data']['activity'], 'id'));
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function task(array $changes): array
    {
        return array_merge([
            'id' => 'xk3j9q2', 'key' => 'WEB-160', 'project' => 'p1', 'title' => 'Draft cookie banner copy', 'status' => 'todo',
            'assignee' => null, 'priority' => 'none', 'due' => null, 'start' => null, 'labels' => [], 'subtasks' => [],
            'attachments' => [], 'deps' => [], 'desc' => '', 'estimate' => null, 'created' => 1792238400000,
            'updated' => 1792238400000, 'order' => 49, 'fav' => false, 'recur' => null,
        ], $changes);
    }

    private function arrangeAcmeLabs(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $ws = new Workspace('Acme Labs', 'acme', '#3B82C4', false, ['pac1']);
        $user = new User('ada@acme.test', 'Ada Byron');
        $user->setPassword($hasher->hashPassword($user, 'acme-password'));
        $member = Member::joined('mac1', $ws, $user, Role::Owner, '', 'Founder', '#3B82C4', 0, 'London');
        $project = new Project('pac1', $ws);
        $project->update([
            'key' => 'ACME', 'name' => 'Rocket Skates', 'icon' => 'rocket', 'color' => 'blue', 'status' => 'active', 'team' => '',
            'lead' => 'mac1', 'due' => null, 'start' => null, 'fav' => false, 'members' => ['mac1'], 'desc' => '', 'milestones' => [], 'last' => 0,
        ]);
        $task = new Task('tac1', $ws);
        $task->update(self::task(['key' => 'ACME-1', 'project' => 'pac1']), $project, $member);
        foreach ([$ws, $user, $member, $project, $task] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
    }
}
