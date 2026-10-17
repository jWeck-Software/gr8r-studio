<?php

namespace App\Tests\Api;

/**
 * Events are dated meetings on a Project's calendar, shared in the Workspace: whoever sees the
 * Project sees them, and whoever may change the Project adds, moves and deletes them. Customer
 * Portal (p6) is the demo's Private project, with Marcus (m6) and Lena (m7) as its Members.
 */
final class EventsTest extends ApiTestCase
{
    private const array REVIEW = ['id' => 'eq7x2', 'title' => 'Copy review', 'date' => '2026-10-21', 'time' => '13:30', 'project' => 'p1'];

    public function testTheDemoEventsAreDatedRelativeToToday(): void
    {
        $this->signIn();

        self::assertSame([
            ['id' => 'e1', 'title' => 'Design review', 'date' => '2026-10-18', 'time' => '10:00', 'project' => 'p1'],
            ['id' => 'e2', 'title' => 'Sprint planning', 'date' => '2026-10-22', 'time' => '09:30', 'project' => 'p2'],
            ['id' => 'e3', 'title' => 'Launch sync', 'date' => '2026-10-17', 'time' => '15:00', 'project' => 'p4'],
            ['id' => 'e4', 'title' => 'Stakeholder demo', 'date' => '2026-10-26', 'time' => '14:00', 'project' => 'p1'],
            ['id' => 'e5', 'title' => 'Campaign kickoff', 'date' => '2026-10-14', 'time' => '11:00', 'project' => 'p3'],
            ['id' => 'e6', 'title' => 'Retro', 'date' => '2026-10-29', 'time' => '16:00', 'project' => 'p2'],
        ], $this->getJson('/api/bootstrap')['data']['events']);
    }

    public function testSarahAddsAnEventAndJohnSeesIt(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'put', 'type' => 'events', 'id' => 'eq7x2', 'data' => self::REVIEW]]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo('john@gr8rstudio.com');
        self::assertSame(self::REVIEW, $this->event('eq7x2'));
    }

    public function testJohnMovesAnEventToAnotherDayAndProject(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $moved = ['id' => 'e1', 'title' => 'Design review, round 2', 'date' => '2026-10-20', 'time' => '11:15', 'project' => 'p2'];

        $body = $this->sync([['op' => 'put', 'type' => 'events', 'id' => 'e1', 'data' => $moved]]);

        self::assertSame(['ok' => true], $body);
        $this->switchTo(self::OWNER_EMAIL);
        self::assertSame($moved, $this->event('e1'));
    }

    public function testTheOwnerDeletesAnEvent(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'delete', 'type' => 'events', 'id' => 'e5'], ['op' => 'delete', 'type' => 'events', 'id' => 'eunknown']]);

        self::assertSame(['ok' => true], $body);
        self::assertNull($this->event('e5'));
    }

    public function testDeletingAProjectDeletesItsEvents(): void
    {
        $this->signIn();

        $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'p2'], ['op' => 'delete', 'type' => 'events', 'id' => 'e2']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['e1', 'e3', 'e4', 'e5'], array_column($this->getJson('/api/bootstrap')['data']['events'], 'id'));
    }

    /** Later ops of the batch see the Project's Events gone, so a put of one of them makes it anew. */
    public function testAnEventPutAgainAfterItsProjectWasDeletedInTheSameBatchIsKept(): void
    {
        $this->signIn();
        $e2 = $this->event('e2');

        $this->sync([
            ['op' => 'put', 'type' => 'events', 'id' => 'e2', 'data' => [...$e2, 'title' => 'Sprint planning (moved)']],
            ['op' => 'delete', 'type' => 'projects', 'id' => 'p2'],
            ['op' => 'put', 'type' => 'events', 'id' => 'e2', 'data' => [...$e2, 'project' => 'p1']],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([...$e2, 'project' => 'p1'], $this->event('e2'));
    }

    public function testEventsOfAPrivateProjectAreSeenAndChangedOnlyByItsMembers(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'events', 'id' => 'eq7x2', 'data' => [...self::REVIEW, 'project' => 'p6']]]);
        self::assertResponseStatusCodeSame(200);

        $this->switchTo(self::OWNER_EMAIL);

        self::assertNull($this->event('eq7x2'));
        foreach ([['op' => 'put', 'type' => 'events', 'id' => 'eq7x2', 'data' => [...self::REVIEW, 'project' => 'p1']], ['op' => 'delete', 'type' => 'events', 'id' => 'eq7x2'], ['op' => 'put', 'type' => 'events', 'id' => 'e1', 'data' => [...self::REVIEW, 'id' => 'e1', 'project' => 'p6']]] as $op) {
            $body = $this->sync([$op]);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('You may not change the Events of the Project "p6".', $body['message']);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function wrongEvents(): iterable
    {
        yield 'no title' => [[...self::REVIEW, 'title' => ' '], 'The Event\'s "title" must be a non-empty string.'];
        yield 'a date that is no date' => [[...self::REVIEW, 'date' => '2026-02-30'], 'The Event\'s "date" must be a date YYYY-MM-DD.'];
        yield 'a time of 25 o\'clock' => [[...self::REVIEW, 'time' => '25:00'], 'The Event\'s "time" must be a time HH:MM.'];
        yield 'an unknown Project' => [[...self::REVIEW, 'project' => 'p99'], 'The Event\'s Project "p99" does not exist.'];
    }

    /** @param array<string, mixed> $event */
    #[\PHPUnit\Framework\Attributes\DataProvider('wrongEvents')]
    public function testAnEventOfTheWrongShapeIsRefused(array $event, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'events', 'id' => 'eq7x2', 'data' => $event]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    /**
     * @param list<array<string, mixed>> $ops
     *
     * @return array<mixed>
     */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** @return array<string, mixed>|null the Event as /api/bootstrap shows it */
    private function event(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['events'], null, 'id')[$id] ?? null;
    }
}
