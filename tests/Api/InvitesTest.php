<?php

namespace App\Tests\Api;

use App\Entity\Member;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invites as an invited person meets them: an email with a link, and accepting it by choosing a
 * name and a password. Sarah is an Admin; Ana is someone new.
 */
final class InvitesTest extends ApiTestCase
{
    private const string LINK = '#^http://localhost:5173/\#join/([A-Za-z0-9_-]{43})$#m';

    public function testInvitingAnaEmailsHerALinkToJoin(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $this->invite();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertSame('ana@gr8rstudio.com', $email->getTo()[0]->getAddress());
        self::assertSame('Sarah Chen invited you to Gr8r Studio', $email->getSubject());
        self::assertMatchesRegularExpression(self::LINK, (string) $email->getTextBody());
    }

    /** The invite and a change Sarah may not make go out in one save: neither is kept, nobody is emailed. */
    public function testARefusedSaveEmailsNobody(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $owner = array_column($this->getJson('/api/bootstrap')['data']['members'], null, 'id')['m1'];

        $this->postJson('/api/sync', ['ops' => [
            ['op' => 'put', 'type' => 'members', 'id' => 'mq7x2', 'data' => self::invited()],
            ['op' => 'put', 'type' => 'members', 'id' => 'm1', 'data' => [...$owner, 'role' => 'Admin']],
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertEmailCount(0);
    }

    /** The whole path: the Owner invites Ana, she follows the link in the email and is in. */
    public function testAnaAcceptsHerInviteAndIsSignedInAsTheNewMember(): void
    {
        $this->signIn();
        $this->invite();
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        $body = $this->accept($token, 'Ana Lopez', 'ana-password-1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['user' => ['email' => 'ana@gr8rstudio.com', 'name' => 'Ana Lopez'], 'member' => 'mq7x2'], $body);
        self::assertSame($body, $this->getJson('/api/me'));
        $ana = $this->member('mq7x2');
        self::assertSame(['Ana Lopez', 'active', 'Member', 'design'], [$ana['name'], $ana['status'], $ana['role'], $ana['team']]);
        $this->client->request('POST', '/api/logout');
        self::assertSame('mq7x2', $this->signIn('ana@gr8rstudio.com', 'ana-password-1')['member']);
    }

    /** What the page behind the link shows before Ana joins: who is invited where, and that she has no account yet. */
    public function testTheLinkTellsWhoIsInvitedWhere(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        $body = $this->getJson('/api/invites/'.$token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['email' => 'ana@gr8rstudio.com', 'name' => 'Ana', 'workspace' => 'Gr8r Studio', 'account' => false], $body);
    }

    public function testTheLinkTellsEmmaSheHasAnAccount(): void
    {
        $token = $this->inviteEmmaAgain();

        self::assertTrue($this->getJson('/api/invites/'.$token)['account']);
    }

    public function testAnUnknownLinkTellsNothing(): void
    {
        $body = $this->getJson('/api/invites/'.str_repeat('x', 43));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_found', 'message' => 'This invite link is not valid any more. It may have been used already or replaced by a newer one.'], $body);
    }

    public function testTheLinkWorksOnce(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->accept($token, 'Ana Lopez', 'ana-password-1');
        $this->client->request('POST', '/api/logout');

        $body = $this->accept($token, 'Someone Else', 'other-password-2');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_found', 'message' => 'This invite link is not valid any more. It may have been used already or replaced by a newer one.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testALinkAfterAWeekHasExpired(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->client->request('POST', '/api/logout');
        self::mockTime(new \DateTimeImmutable('2026-10-24 12:00:01', new \DateTimeZone('UTC')));

        $body = $this->accept($token, 'Ana Lopez', 'ana-password-1');

        self::assertResponseStatusCodeSame(410);
        self::assertSame(['error' => 'expired', 'message' => 'This invite has expired. Ask an Owner or Admin of the Workspace to send you a new one.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRemovingAnaBeforeSheJoinsMakesHerLinkInvalid(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'members', 'id' => 'mq7x2']]]);
        $this->client->request('POST', '/api/logout');

        $this->accept($token, 'Ana Lopez', 'ana-password-1');

        self::assertResponseStatusCodeSame(404);
    }

    /** Sending the Invite again emails a new link; the one Ana got first stops working. */
    public function testSarahSendsAnasInviteAgain(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $first = $this->token();

        $this->client->request('POST', '/api/members/mq7x2/invite');

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
        $second = $this->token();
        self::assertNotSame($first, $second);
        $this->client->request('POST', '/api/logout');
        $this->accept($first, 'Ana Lopez', 'ana-password-1');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('mq7x2', $this->accept($second, 'Ana Lopez', 'ana-password-1')['member']);
    }

    /** Diego was invited before Invites were emailed (the demo seed): sending it again gives him his first link. */
    public function testSarahSendsDiegosInviteAgain(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $this->client->request('POST', '/api/members/m8/invite');

        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertSame('diego@freelance.io', $email->getTo()[0]->getAddress());
        self::assertSame('Sarah Chen invited you to Gr8r Studio', $email->getSubject());
        $token = $this->token();
        $this->client->request('POST', '/api/logout');
        self::assertSame('m8', $this->accept($token, 'Diego Alvarez', 'diego-password-1')['member']);
    }

    /** @return iterable<string, array{string, string, int, array{error: string, message: string}}> */
    public static function refusedResends(): iterable
    {
        yield 'by John, a Member' => ['john@gr8rstudio.com', 'm8', 403, ['error' => 'forbidden', 'message' => 'Only Owners and Admins invite people.']];
        yield 'to John, who joined' => ['sarah@gr8rstudio.com', 'm3', 422, ['error' => 'invalid', 'message' => 'John Carter has joined already.']];
        yield 'to someone unknown' => ['sarah@gr8rstudio.com', 'mzz9', 404, ['error' => 'not_found', 'message' => 'This Workspace has no such Member.']];
    }

    /** @param array{error: string, message: string} $expected */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedResends')]
    public function testSendingAnInviteAgainIsRefused(string $email, string $id, int $status, array $expected): void
    {
        $this->signIn($email);

        $this->client->request('POST', '/api/members/'.$id.'/invite');

        self::assertResponseStatusCodeSame($status);
        self::assertSame($expected, $this->lastJson());
        self::assertEmailCount(0);
    }

    /** Only an Owner invites an Owner, also the second time. */
    public function testSarahAnAdminDoesNotSendTheInviteOfAnOwnerAgain(): void
    {
        $this->signIn();
        $this->invite('mq7x2', ['role' => 'Owner']);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');

        $this->client->request('POST', '/api/members/mq7x2/invite');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'message' => 'Only an Owner changes an Owner\'s Role or makes someone an Owner.'], $this->lastJson());
    }

    /** @return iterable<string, array{string}> */
    public static function weakPasswords(): iterable
    {
        yield 'under 10 characters' => ['short-1'];
        yield 'letters only' => ['longpassword'];
        yield 'an underscore is no symbol' => ['under_score_x'];
    }

    /** As the app asks: at least 10 characters, including a number or symbol. */
    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswords')]
    public function testAWeakPasswordIsRefusedAndTheLinkStillWorks(string $password): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        $body = $this->accept($token, 'Ana Lopez', $password);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Use at least 10 characters, including a number or symbol.'], $body);
        self::assertSame('mq7x2', $this->accept($token, 'Ana Lopez', 'ana-password-1')['member']);
    }

    public function testAcceptingWithoutANameIsRefused(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        $body = $this->accept($token, ' ', 'ana-password-1');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'Enter your name.'], $body);
    }

    public function testAcceptingWhileSignedInAsSomeoneElseSignsInAsTheNewMember(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite();
        $token = $this->token();

        $this->accept($token, 'Ana Lopez', 'ana-password-1');

        self::assertSame(['user' => ['email' => 'ana@gr8rstudio.com', 'name' => 'Ana Lopez'], 'member' => 'mq7x2'], $this->getJson('/api/me'));
    }

    /**
     * Emma was removed, so her User has no Workspace; invited again, she joins with the password she
     * already has instead of choosing a new one.
     */
    public function testEmmaRemovedAndInvitedAgainJoinsWithHerOwnPassword(): void
    {
        $token = $this->inviteEmmaAgain();

        $body = $this->accept($token, 'Emma Wilson', self::DEMO_PASSWORD);

        self::assertSame(['user' => ['email' => 'emma@gr8rstudio.com', 'name' => 'Emma Wilson'], 'member' => 'mq7x2'], $body);
        self::assertSame('active', $this->member('mq7x2')['status']);
        $this->client->request('POST', '/api/logout');
        self::assertSame('mq7x2', $this->signIn('emma@gr8rstudio.com')['member']);
    }

    public function testEmmaInvitedAgainCannotJoinWithAnotherPassword(): void
    {
        $token = $this->inviteEmmaAgain();

        $body = $this->accept($token, 'Emma Wilson', 'a-new-password-1');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'invalid_credentials', 'message' => 'emma@gr8rstudio.com already has an account: enter its password to join.'], $body);
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
        self::assertSame('mq7x2', $this->accept($token, 'Emma Wilson', self::DEMO_PASSWORD)['member']);
    }

    /** Switching between Workspaces is out of scope: one User is a Member of one Workspace. */
    public function testAdaAMemberOfAnotherWorkspaceCannotJoinThisOneToo(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $acme = new Workspace('Acme Labs', 'acme', '#3B82C4', false, []);
        $ada = new User('ada@acme.test', 'Ada Byron');
        $ada->setPassword($hasher->hashPassword($ada, 'acme-password'));
        foreach ([$acme, $ada, new Member('mac1', $acme, 'Ada Byron', 'ada@acme.test', 'Owner', '', 'Founder', '#3B82C4', 'active', 0, 'London', $ada)] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite('mq7x2', ['name' => 'Ada', 'email' => 'ada@acme.test']);
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        $body = $this->accept($token, 'Ada Byron', 'acme-password');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'conflict', 'message' => 'ada@acme.test is a Member of another Workspace already, and an account belongs to one Workspace for now.'], $body);
        self::assertSame('mac1', $this->signIn('ada@acme.test', 'acme-password')['member']);
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<mixed>
     */
    private function invite(string $id = 'mq7x2', array $changes = []): array
    {
        return $this->postJson('/api/sync', ['ops' => [['op' => 'put', 'type' => 'members', 'id' => $id, 'data' => self::invited($id, $changes)]]]);
    }

    /** The Owner removes Emma, Sarah invites her again; returns the token of her link, signed out. */
    private function inviteEmmaAgain(): string
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'members', 'id' => 'm4']]]);
        $this->client->request('POST', '/api/logout');
        $this->signIn('sarah@gr8rstudio.com');
        $this->invite('mq7x2', ['name' => 'Emma', 'email' => 'emma@gr8rstudio.com']);
        $token = $this->token();
        $this->client->request('POST', '/api/logout');

        return $token;
    }

    /** The token in the link of the last email sent; the mails of a request are gone after the next one. */
    private function token(): string
    {
        $email = self::getMailerMessage();
        \assert($email instanceof Email);
        self::assertMatchesRegularExpression(self::LINK, (string) $email->getTextBody());
        preg_match(self::LINK, (string) $email->getTextBody(), $match);

        return $match[1];
    }

    /** @return array<mixed> */
    private function accept(string $token, string $name, string $password): array
    {
        return $this->postJson('/api/invites/'.$token.'/accept', ['name' => $name, 'password' => $password]);
    }

    /** @return array<string, mixed>|null the Member as /api/bootstrap shows it */
    private function member(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['members'], null, 'id')[$id] ?? null;
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function invited(string $id = 'mq7x2', array $changes = []): array
    {
        return [
            'id' => $id, 'name' => 'Ana', 'email' => 'ana@gr8rstudio.com', 'role' => 'Member', 'team' => 'design', 'title' => 'Member',
            'c' => '#6B7280', 'status' => 'invited', 'last' => null, 'tz' => '—', ...$changes,
        ];
    }
}
