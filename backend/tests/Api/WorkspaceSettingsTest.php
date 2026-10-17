<?php

namespace App\Tests\Api;

use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

/** Owners and Admins rename the Workspace, change its URL and colour. Sarah is an Admin, John a Member. */
final class WorkspaceSettingsTest extends ApiTestCase
{
    private const array RENAMED = ['name' => 'Gr8r Labs', 'url' => 'gr8r-labs', 'c' => '#5A67D8', 'brand' => true];

    public function testSarahRenamesAndRecoloursTheWorkspace(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->setWs(self::RENAMED);

        self::assertSame(['ok' => true], $body);
        $this->client->request('POST', '/api/logout');
        $this->signIn('john@gr8rstudio.com');
        self::assertSame(self::RENAMED, $this->getJson('/api/bootstrap')['data']['ws']);
    }

    public function testJohnAMemberChangesNoWorkspaceSettings(): void
    {
        $this->signIn('john@gr8rstudio.com');

        $body = $this->setWs(self::RENAMED);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'Only Owners and Admins change the Workspace\'s settings.'], $body);
        self::assertSame('Gr8r Studio', $this->getJson('/api/bootstrap')['data']['ws']['name']);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidSettings(): iterable
    {
        $slug = 'The Workspace URL must be lowercase letters and digits, with single hyphens between them, like gr8r-studio.';
        yield 'not an object' => [['Gr8r Labs'], 'The Workspace settings must be {name, url, c, brand}.'];
        yield 'empty name' => [[...self::RENAMED, 'name' => ' '], 'The Workspace\'s "name" must be a non-empty string.'];
        yield 'URL with spaces' => [[...self::RENAMED, 'url' => 'gr8r labs'], $slug];
        yield 'URL in capitals' => [[...self::RENAMED, 'url' => 'Gr8rLabs'], $slug];
        yield 'URL ending in a hyphen' => [[...self::RENAMED, 'url' => 'gr8r-labs-'], $slug];
        yield 'URL with two hyphens' => [[...self::RENAMED, 'url' => 'gr8r--labs'], $slug];
        yield 'empty URL' => [[...self::RENAMED, 'url' => ''], $slug];
        yield 'colour by name' => [[...self::RENAMED, 'c' => 'black'], 'The Workspace\'s "c" must be a colour like #5A67D8.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSettings')]
    public function testInvalidSettingsAreRefused(mixed $ws, string $message): void
    {
        $this->signIn();

        $body = $this->setWs($ws);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    public function testAnotherWorkspacesUrlIsTaken(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new Workspace('Acme Labs', 'acme', '#3B82C4'));
        $em->flush();
        $this->signIn();

        $body = $this->setWs([...self::RENAMED, 'url' => 'acme']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Workspace URL "acme" is taken.'], $body);
    }

    /**
     * @param array<string, mixed>|list<mixed> $ws
     *
     * @return array<mixed>
     */
    private function setWs(mixed $ws): array
    {
        return $this->postJson('/api/sync', ['ops' => [['op' => 'set', 'type' => 'ws', 'data' => $ws]]]);
    }
}
