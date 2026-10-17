<?php

namespace App\Tests\Api;

/**
 * The Owner (m1) deletes the demo Workspace "Gr8r Studio", typing its name to confirm, as the
 * app's dialog asks. Sarah (m2) is one of its Admins.
 */
final class DeletingAWorkspaceTest extends ApiTestCase
{
    public function testTheOwnerDeletesTheWorkspace(): void
    {
        $this->signIn();

        $body = $this->delete('Gr8r Studio');

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $body);
        self::assertSame('no_workspace', $this->getJson('/api/me')['error']);
        self::assertResponseStatusCodeSame(403);
        $this->getJson('/api/bootstrap');
        self::assertResponseStatusCodeSame(403);
    }

    /** Everyone in it is signed out in effect: Sarah's session finds no Workspace, and signing in again neither. */
    public function testEveryoneInItIsLeftWithoutAWorkspace(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $sarah = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->signIn();

        $this->delete('Gr8r Studio');

        $this->client->getCookieJar()->clear();
        foreach ($sarah as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        self::assertSame('no_workspace', $this->getJson('/api/bootstrap')['error']);
        self::assertResponseStatusCodeSame(403);
        $this->postJson('/api/sync', ['ops' => [['op' => 'set', 'type' => 'projOrder', 'data' => []]]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/logout');
        self::assertSame(['user' => ['email' => 'sarah@gr8rstudio.com', 'name' => 'Sarah Chen'], 'member' => null], $this->signIn('sarah@gr8rstudio.com'));
    }

    /**
     * The content of its Files goes too, once the deletion is stored. The API can't tell a File
     * without content from one without a record, so this looks into the storage directory.
     */
    public function testTheContentOfItsFilesGoes(): void
    {
        $this->signIn();
        $before = $this->stored('f4');

        $this->delete('Gr8r Studio');

        // Earlier tests' demo Workspaces left theirs (their database rows were rolled back): this one's is the newest.
        self::assertSame(\array_slice($before, 0, -1), $this->stored('f4'));
    }

    /** An Invite into it no longer leads anywhere. */
    public function testItsInviteLinksStopWorking(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => [
            'id' => 'mq7x2', 'name' => 'Nina', 'email' => 'nina@example.test', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—',
        ]]]]);
        $mail = self::getMailerMessage();
        \assert($mail instanceof \Symfony\Component\Mime\Email);
        preg_match('#\#join/([A-Za-z0-9_-]{43})#', (string) $mail->getTextBody(), $join);

        $this->delete('Gr8r Studio');

        $this->getJson('/api/invites/'.$join[1]);
        self::assertResponseStatusCodeSame(404);
        $this->postJson('/api/invites/'.$join[1].'/accept', ['name' => 'Nina', 'password' => 'nina-password-1']);
        self::assertResponseStatusCodeSame(404);
    }

    /** The Owner, left without a Workspace, can start one again, even under the URL the deleted one had. */
    public function testTheOwnerCanStartAgain(): void
    {
        $this->signIn();
        $this->delete('Gr8r Studio');

        $this->postJson('/api/workspaces', ['name' => 'Gr8r Studio', 'url' => 'gr8rstudio', 'c' => '#1D1C1A']);

        self::assertResponseStatusCodeSame(201);
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertSame([[], 1], [$data['projects'], \count($data['members'])]);
    }

    /** @return iterable<string, array{string}> */
    public static function otherNames(): iterable
    {
        yield 'another name' => ['Gr8r'];
        yield 'in other case' => ['gr8r studio'];
        yield 'nothing' => [''];
    }

    /** Only the Workspace's name, exactly as the dialog asks for it, confirms; nothing is deleted otherwise. */
    #[\PHPUnit\Framework\Attributes\DataProvider('otherNames')]
    public function testAnotherNameDeletesNothing(string $name): void
    {
        $this->signIn();

        $body = $this->delete($name);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Type the Workspace\'s name, "Gr8r Studio", to delete it.'], $body);
        self::assertSame('Gr8r Studio', $this->getJson('/api/bootstrap')['data']['ws']['name']);
        $this->client->request('GET', '/api/files/f4/content');
        self::assertResponseStatusCodeSame(200);
    }

    /** @return iterable<string, array{string}> */
    public static function notOwners(): iterable
    {
        yield 'an Admin' => ['sarah@gr8rstudio.com'];
        yield 'a Member' => ['john@gr8rstudio.com'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notOwners')]
    public function testOnlyAnOwnerDeletesTheWorkspace(string $email): void
    {
        $this->signIn($email);

        $body = $this->delete('Gr8r Studio');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'message' => 'Only an Owner deletes the Workspace.'], $body);
        self::assertSame('Gr8r Studio', $this->getJson('/api/bootstrap')['data']['ws']['name']);
    }

    /** @return list<string> the stored contents of the File with this id, in any Workspace, oldest Workspace first */
    private function stored(string $fileId): array
    {
        $paths = glob(self::getContainer()->getParameter('kernel.project_dir').'/var/test-storage/*/'.$fileId) ?: [];
        usort($paths, static fn (string $a, string $b): int => (int) basename(\dirname($a)) <=> (int) basename(\dirname($b)));

        return $paths;
    }

    /** @return array<mixed> */
    private function delete(string $name): array
    {
        $this->client->request('DELETE', '/api/workspace', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['name' => $name], \JSON_THROW_ON_ERROR));

        return $this->lastJson();
    }
}
