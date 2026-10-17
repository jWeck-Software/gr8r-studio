<?php

namespace App\Tests\Api;

/**
 * Comments on Tasks and their Reactions. The demo has nine Comments; c1 to c3 are on
 * "Create homepage wireframes" (t3), written by Emma (m4), Sarah (m2) and John (m3).
 */
final class CommentsTest extends ApiTestCase
{
    public function testBootstrapCarriesTheCommentsOldestFirst(): void
    {
        $this->signIn();

        $comments = $this->getJson('/api/bootstrap')['data']['comments'];

        self::assertSame(['c8', 'c7', 'c4', 'c1', 'c2', 'c9', 'c5', 'c3', 'c6'], array_column($comments, 'id'));
        self::assertRecord([
            'id' => 'c1',
            'task' => 't3',
            'by' => 'm4',
            'at' => 1792222800000,
            'text' => 'Tablet layout feels crowded around the logo strip. Could we drop to four logos under 1024px?',
            're' => ['👍' => ['m2', 'm3']],
        ], $comments[3]);
    }

    /** The frontend adds a Reaction with `re[emoji] = [...]`, which an array would silently drop. */
    public function testCommentsWithoutReactionsCarryAnEmptyObject(): void
    {
        $this->signIn();
        $this->client->request('GET', '/api/bootstrap');

        $comments = json_decode((string) $this->client->getResponse()->getContent())->data->comments;

        self::assertEquals(new \stdClass(), array_column($comments, null, 'id')['c2']->re);
    }

    public function testAMemberCommentsAndTheCommentIsTheirsWhateverTheClientSays(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['by' => 'm2'])]]);

        self::assertSame(['ok' => true], $body);
        self::assertRecord(['id' => 'cx7k2m9', 'task' => 't4', 'by' => 'm1', 'at' => 1792238400000, 'text' => 'Ship it @John Carter', 're' => []], $this->comment('cx7k2m9'));
    }

    public function testACommentOnAnUnknownTaskIsRefused(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['task' => 'tq0zz'])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The Comment\'s Task "tq0zz" does not exist.'], $body);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidComments(): iterable
    {
        yield 'no text' => [['text' => '  '], 'The Comment\'s "text" must be a non-empty string.'];
        yield 'no time' => [['at' => '2026-10-17'], 'The Comment\'s "at" must be epoch milliseconds.'];
        yield 'Reactions as a list' => [['re' => ['👍']], 'The Comment\'s "re" must be an object of lists of strings.'];
        yield 'a Reaction that is not a list of ids' => [['re' => ['👍' => 'm1']], 'The Comment\'s "re" must be an object of lists of strings.'];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidComments')]
    public function testInvalidCommentsAreRefused(array $changes, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment($changes)]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    /** The Owner is not a Member of the Private project Customer Portal (p6) and its Task t49. */
    public function testACommentOnATaskOfAPrivateProjectOneCannotSeeIsForbidden(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['task' => 't49'])]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not comment on the Task "t49".'], $body);
    }

    public function testCommentsInAPrivateProjectAreSeenOnlyByItsMembers(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['task' => 't49', 'text' => 'Contract review is done.'])]]);
        self::assertResponseStatusCodeSame(200);

        $this->switchTo('lena@gr8rstudio.com');
        self::assertSame('Contract review is done.', $this->comment('cx7k2m9')['text']);
        $this->switchTo(self::OWNER_EMAIL);
        self::assertNull($this->comment('cx7k2m9'));
    }

    public function testTheAuthorDeletesTheirComment(): void
    {
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'delete', 'type' => 'comments', 'id' => 'c2']]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(['c1', 'c3'], array_values(array_intersect(['c1', 'c2', 'c3'], array_column($this->getJson('/api/bootstrap')['data']['comments'], 'id'))));
    }

    public function testDeletingSomeoneElsesCommentIsForbiddenEvenForTheOwner(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'delete', 'type' => 'comments', 'id' => 'c2']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'Only its author may delete the Comment "c2".'], $body);
        self::assertSame('m2', $this->comment('c2')['by']);
    }

    /** Writes to records one can't see are refused: Lena, taken off the Private Customer Portal (p6), can't delete her Comment there. */
    public function testDeletingOnesCommentOnATaskOneNoLongerSeesIsForbidden(): void
    {
        $this->signIn('lena@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['task' => 't49', 'by' => 'm7', 'text' => 'Contract review is done.'])]]);
        $this->switchTo('marcus@gr8rstudio.com');
        $p6 = array_column($this->getJson('/api/bootstrap')['data']['projects'], null, 'id')['p6'];
        $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p6', 'data' => [...$p6, 'members' => ['m6']]]]);
        self::assertResponseStatusCodeSame(200);
        $this->switchTo('lena@gr8rstudio.com');

        $body = $this->sync([['op' => 'delete', 'type' => 'comments', 'id' => 'cx7k2m9']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not delete the Comment "cx7k2m9".'], $body);
        $this->switchTo('marcus@gr8rstudio.com');
        self::assertSame('Contract review is done.', $this->comment('cx7k2m9')['text']);
    }

    public function testDeletingAnUnknownCommentIsANoOp(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'delete', 'type' => 'comments', 'id' => 'cq0zz']]);

        self::assertSame(['ok' => true], $body);
    }

    public function testAMemberReactsToSomeoneElsesComment(): void
    {
        $this->signIn();
        $c1 = $this->comment('c1');

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c1', 'data' => [...$c1, 're' => ['👍' => ['m2', 'm3', 'm1'], '🎉' => ['m1']]]]]);

        self::assertSame(['ok' => true], $body);
        self::assertRecord([
            'id' => 'c1',
            'task' => 't3',
            'by' => 'm4',
            'at' => 1792222800000,
            'text' => 'Tablet layout feels crowded around the logo strip. Could we drop to four logos under 1024px?',
            're' => ['👍' => ['m2', 'm3', 'm1'], '🎉' => ['m1']],
        ], $this->comment('c1'));
    }

    /** @return iterable<string, array{array<string, mixed>}> changes the Owner makes to Emma's c1 */
    public static function changesToSomeoneElsesComment(): iterable
    {
        yield 'edit its text' => [['text' => 'Four logos it is.']];
        yield 'move it to another Task' => [['task' => 't4']];
        yield 'backdate it' => [['at' => 1792222700000]];
        yield 'claim it' => [['by' => 'm1']];
        yield 'react for someone else' => [['re' => ['👍' => ['m2', 'm3'], '🎉' => ['m5']]]];
        yield 'take back someone else\'s Reaction' => [['re' => ['👍' => ['m2']]]];
        yield 'react and take back someone else\'s Reaction' => [['re' => ['👍' => ['m2', 'm1']]]];
    }

    /** @param array<string, mixed> $changes */
    #[\PHPUnit\Framework\Attributes\DataProvider('changesToSomeoneElsesComment')]
    public function testOnSomeoneElsesCommentAMemberMayOnlyAddOrRemoveTheirOwnReaction(array $changes): void
    {
        $this->signIn();
        $c1 = $this->comment('c1');

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c1', 'data' => [...$c1, ...$changes]]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'On the Comment "c1" by someone else you may only add or remove your own Reactions.'], $body);
        self::assertRecord($c1, $this->comment('c1'));
    }

    public function testTheAuthorEditsTheirCommentButNotOthersReactions(): void
    {
        $this->signIn('john@gr8rstudio.com');
        $c3 = $this->comment('c3');

        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c3', 'data' => [...$c3, 'text' => 'Hero spacing maps to the 8pt scale.']]]);
        self::assertResponseStatusCodeSame(200);
        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c3', 'data' => [...$c3, 're' => []]]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may only add or remove your own Reactions on the Comment "c3".'], $body);
        self::assertRecord([...$c3, 'text' => 'Hero spacing maps to the 8pt scale.'], $this->comment('c3'));
    }

    public function testTheAuthorCannotMoveTheirCommentToAnotherTask(): void
    {
        $this->signIn('john@gr8rstudio.com');

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c3', 'data' => [...$this->comment('c3'), 'task' => 't4']]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('A Comment keeps its Task, author and time; "task" cannot change.', $body['message']);
        self::assertSame('t3', $this->comment('c3')['task']);
    }

    public function testAMemberTakesBackTheirReaction(): void
    {
        $this->signIn('sarah@gr8rstudio.com');
        $c3 = $this->comment('c3');

        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'c3', 'data' => [...$c3, 're' => ['🎉' => []]]]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->comment('c3')['re']['🎉']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function deletionsOfATaskOrItsProject(): iterable
    {
        yield 'the Task' => [['op' => 'delete', 'type' => 'tasks', 'id' => 't3']];
        yield 'its Project' => [['op' => 'delete', 'type' => 'projects', 'id' => 'p1']];
    }

    /**
     * The frontend drops the Comments of a deleted Task from S.data, so its batch deletes them
     * after the Task, others' Comments included.
     *
     * @param array<string, mixed> $deletion
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deletionsOfATaskOrItsProject')]
    public function testDeletingATaskOrItsProjectDeletesItsComments(array $deletion): void
    {
        $this->signIn();
        $c1 = $this->comment('c1');
        $t3 = array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id')['t3'];

        $body = $this->sync([
            ['op' => 'put', 'type' => 'comments', 'id' => 'c1', 'data' => [...$c1, 're' => ['👍' => ['m2', 'm3', 'm1']]]],
            $deletion,
            ['op' => 'delete', 'type' => 'comments', 'id' => 'c1'],
            ['op' => 'delete', 'type' => 'comments', 'id' => 'c2'],
            ['op' => 'delete', 'type' => 'comments', 'id' => 'c3'],
        ]);

        self::assertSame(['ok' => true], $body);
        if ('projects' === $deletion['type']) {
            $this->sync([['op' => 'put', 'type' => 'projects', 'id' => 'p1', 'data' => ['id' => 'p1', 'key' => 'WEB', 'name' => 'Website Redesign', 'icon' => 'globe', 'color' => 'indigo', 'status' => 'active', 'team' => 'design', 'lead' => 'm2', 'due' => null, 'start' => null, 'fav' => false, 'members' => [], 'desc' => '', 'milestones' => [], 'last' => 0]]]);
        }
        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't3', 'data' => $t3]]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame([], array_values(array_filter($this->getJson('/api/bootstrap')['data']['comments'], static fn (array $c): bool => 't3' === $c['task'])));
    }

    /**
     * Undo puts back the Task and its Comments as they were; each Comment stays its author's.
     *
     * @param array<string, mixed> $deletion
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deletionsOfATaskOrItsProject')]
    public function testUndoingTheDeletionOfATaskOrItsProjectKeepsWhoWroteItsComments(array $deletion): void
    {
        $this->signIn();
        $data = $this->getJson('/api/bootstrap')['data'];
        $comments = array_values(array_filter($data['comments'], static fn (array $c): bool => 't3' === $c['task']));
        $restore = array_map(static fn (array $c): array => ['op' => 'put', 'type' => 'comments', 'id' => $c['id'], 'data' => $c], $comments);
        $this->sync([$deletion, ...array_map(static fn (array $c): array => ['op' => 'delete', 'type' => 'comments', 'id' => $c['id']], $comments)]);
        $project = array_column($data['projects'], null, 'id')['p1'];
        unset($project['seq']);
        $p1Tasks = array_values(array_filter($data['tasks'], static fn (array $t): bool => 'p1' === $t['project']));

        $body = $this->sync([
            ...('projects' === $deletion['type'] ? [['op' => 'put', 'type' => 'projects', 'id' => 'p1', 'data' => $project]] : []),
            ...array_map(static fn (array $t): array => ['op' => 'put', 'type' => 'tasks', 'id' => $t['id'], 'data' => $t], 'projects' === $deletion['type'] ? $p1Tasks : [array_column($p1Tasks, null, 'id')['t3']]),
            ...$restore,
        ]);

        self::assertSame(['ok' => true], $body);
        $restored = array_values(array_filter($this->getJson('/api/bootstrap')['data']['comments'], static fn (array $c): bool => 't3' === $c['task']));
        self::assertSame([['c1', 'm4'], ['c2', 'm2'], ['c3', 'm3']], array_map(static fn (array $c): array => [$c['id'], $c['by']], $restored));
        self::assertRecord($comments, $restored);
    }

    /** Customer Portal (p6) is Private to Marcus and Lena; the Owner can't bring back a Comment on it. */
    public function testOnlyAMemberWhoSeesItsTaskCanBringBackADeletedComment(): void
    {
        $this->signIn('marcus@gr8rstudio.com');
        $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['task' => 't49', 'text' => 'Contract review is done.'])]]);
        $this->sync([['op' => 'delete', 'type' => 'comments', 'id' => 'cx7k2m9']]);
        $this->switchTo(self::OWNER_EMAIL);

        $body = $this->sync([['op' => 'put', 'type' => 'comments', 'id' => 'cx7k2m9', 'data' => self::newComment(['by' => 'm6', 'text' => 'Contract review is done.'])]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not comment on the Task "t49".'], $body);
        self::assertNull($this->comment('cx7k2m9'));
        $this->switchTo('lena@gr8rstudio.com');
        self::assertNull($this->comment('cx7k2m9'));
    }

    /**
     * A new Comment as the frontend's postComment() sends it.
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function newComment(array $changes = []): array
    {
        return [...['id' => 'cx7k2m9', 'task' => 't4', 'by' => 'm1', 'at' => 1792238400000, 'text' => 'Ship it @John Carter', 're' => []], ...$changes];
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** @param list<array<string, mixed>> $ops */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /** @return array<string, mixed>|null the Comment as /api/bootstrap shows it */
    private function comment(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['comments'], null, 'id')[$id] ?? null;
    }
}
