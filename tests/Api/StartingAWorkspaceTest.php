<?php

namespace App\Tests\Api;

use Symfony\Component\Mime\Email;

/**
 * Onboarding's last step: Nina, verified and signed in without a Workspace, starts her own, with
 * herself as its Owner.
 */
final class StartingAWorkspaceTest extends ApiTestCase
{
    public function testNinaStartsHerWorkspaceAsItsOwner(): void
    {
        $this->signUpAndVerify();

        $body = $this->start();

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['email' => 'nina@example.test', 'name' => 'Nina Novak'], $body['user']);
        self::assertIsString($body['member']);
        self::assertSame($body, $this->getJson('/api/me'));
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['name' => 'Nina Studio', 'url' => 'nina-studio', 'c' => '#23918A', 'brand' => false], $data['ws']);
        self::assertSame($body['member'], $data['me']);
        self::assertCount(1, $data['members']);
        $nina = $data['members'][0];
        self::assertSame([$body['member'], 'Nina Novak', 'nina@example.test', 'Owner', 'active'], [$nina['id'], $nina['name'], $nina['email'], $nina['role'], $nina['status']]);
        self::assertSame([[], [], [], [], [], []], [$data['projects'], $data['tasks'], $data['projOrder'], $data['comments'], $data['files'], $data['events']]);
    }

    /** A new Workspace starts with the app's four Teams and Nina's notification settings at their defaults. */
    public function testTheWorkspaceStartsWithTheDefaultTeamsAndNotificationSettings(): void
    {
        $this->signUpAndVerify();

        $this->start();

        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertSame([
            ['Design', 'palette', '#8662C9', 'Product design, brand, and research'],
            ['Engineering', 'code', '#3B82C4', 'Web, mobile, and platform engineering'],
            ['Marketing', 'megaphone', '#C54B78', 'Campaigns, content, and growth'],
            ['Product', 'target', '#C48A1E', 'Roadmap, planning, and QA'],
        ], array_map(static fn (array $t): array => [$t['name'], $t['icon'], $t['c'], $t['desc']], $data['teams']));
        // Their ids are the new Workspace's own: the demo Workspace has "design", "eng" and so on.
        self::assertSame([], array_intersect(array_column($data['teams'], 'id'), ['design', 'eng', 'mkt', 'product']));
        self::assertRecord([
            'email_mention' => true, 'email_assign' => true, 'email_digest' => true, 'email_comment' => false,
            'push_mention' => true, 'push_assign' => true, 'push_comment' => true, 'push_due' => true,
            'mention_all' => true, 'assign_self' => true,
        ], $data['notifPrefs']);
    }

    /** Onboarding then syncs the first Project as any change is synced; the demo Workspace stays out of sight. */
    public function testNinaSyncsHerFirstProjectIntoIt(): void
    {
        $this->signUpAndVerify();
        $me = $this->start()['member'];

        $this->postJson('/api/sync', ['ops' => [
            ['op' => 'put', 'type' => 'projects', 'id' => 'pq7x2', 'data' => [
                'id' => 'pq7x2', 'key' => 'WL', 'name' => 'Website Launch', 'icon' => 'globe', 'color' => 'indigo', 'status' => 'planning',
                'team' => '', 'lead' => $me, 'due' => '2026-11-16', 'start' => '2026-10-17', 'fav' => false, 'members' => [$me],
                'desc' => 'Created during setup from the Website template.', 'milestones' => [], 'last' => 0,
            ]],
            ['op' => 'set', 'type' => 'projOrder', 'data' => ['pq7x2']],
        ]]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->getJson('/api/bootstrap')['data'];
        self::assertSame(['pq7x2'], array_column($data['projects'], 'id'));
        self::assertSame(['pq7x2'], $data['projOrder']);
        self::assertSame([$me], array_column($data['members'], 'id'));
    }

    public function testAUrlInUseIsRefused(): void
    {
        $this->signUpAndVerify();

        $body = $this->start(url: 'gr8rstudio');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'The Workspace URL "gr8rstudio" is taken.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<string, array{array{name?: string, url?: string, c?: string}, string}> */
    public static function invalidWorkspaces(): iterable
    {
        yield 'no name' => [['name' => ' '], 'Name your workspace.'];
        yield 'a URL that is no slug' => [['url' => 'Nina Studio'], 'The Workspace URL must be lowercase letters and digits, with single hyphens between them, like gr8r-studio.'];
        yield 'a colour that is none' => [['c' => 'teal'], 'The Workspace colour must be like #5A67D8.'];
    }

    /** @param array{name?: string, url?: string, c?: string} $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidWorkspaces')]
    public function testAnInvalidWorkspaceIsRefused(array $changes, string $message): void
    {
        $this->signUpAndVerify();

        $body = $this->postJson('/api/workspaces', [...['name' => 'Nina Studio', 'url' => 'nina-studio', 'c' => '#23918A'], ...$changes]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => $message], $body);
    }

    /** One User is a Member of one Workspace, for now. */
    public function testSarahAMemberOfTheDemoWorkspaceStartsNoOther(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->start();

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'conflict', 'message' => 'You are a Member of a Workspace already, and an account belongs to one Workspace for now.'], $body);
        self::assertSame('m2', $this->getJson('/api/me')['member']);
    }

    public function testStartingAWorkspaceNeedsASignedInUser(): void
    {
        $body = $this->start();

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthenticated', $body['error']);
    }

    /** @return array<mixed> */
    private function start(string $name = 'Nina Studio', string $url = 'nina-studio', string $c = '#23918A'): array
    {
        return $this->postJson('/api/workspaces', ['name' => $name, 'url' => $url, 'c' => $c]);
    }

    /** Nina signs up and opens the link in her email, which signs her in. */
    private function signUpAndVerify(string $email = 'nina@example.test'): void
    {
        $this->postJson('/api/signup', ['name' => 'Nina Novak', 'email' => $email, 'password' => 'nina-password-1']);
        $mail = self::getMailerMessage();
        \assert($mail instanceof Email);
        preg_match('#\#verify/([A-Za-z0-9_-]{43})#', (string) $mail->getTextBody(), $match);
        $this->postJson('/api/verify-email', ['token' => $match[1]]);
        self::assertResponseStatusCodeSame(200);
    }
}
