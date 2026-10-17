<?php

namespace App\Tests\Api;

use App\Entity\Member;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
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
