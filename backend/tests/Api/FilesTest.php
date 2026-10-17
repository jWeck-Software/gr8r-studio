<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Files: uploaded to a Project (p1 Website Redesign), optionally attached to one of its Tasks
 * (t3), downloaded from /api/files/{id}/content, renamed and deleted through the sync.
 * Signed in as the Owner (m1) unless a test says otherwise.
 */
final class FilesTest extends ApiTestCase
{
    /** 2026-10-17 12:00:00 UTC, the tests' "now". */
    private const int NOW = 1792238400000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function testAMemberUploadsAFileToAProject(): void
    {
        $body = $this->upload('brief.pdf', str_repeat('x', 2048), ['id' => 'fup1', 'project' => 'p1']);

        self::assertResponseStatusCodeSame(201);
        $file = ['id' => 'fup1', 'project' => 'p1', 'name' => 'brief.pdf', 'type' => 'pdf', 'size' => '2 KB', 'by' => 'm1', 'at' => self::NOW, 'task' => null];
        self::assertRecord($file, $body);
        self::assertRecord($file, $this->file('fup1'));
    }

    public function testTheDemoFilesHaveContentOfTheirType(): void
    {
        foreach (['f4' => 'image/png', 'f6' => 'application/pdf', 'f8' => 'application/json', 'f3' => 'application/zip', 'f7' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $id => $type) {
            $this->client->request('GET', "/api/files/$id/content");

            self::assertResponseStatusCodeSame(200);
            self::assertSame($type, $this->client->getInternalResponse()->getHeader('Content-Type'), $id);
        }
        $this->client->request('GET', '/api/files/f4/content');
        self::assertStringStartsWith("\x89PNG\r\n", $this->client->getInternalResponse()->getContent());
        $this->client->request('GET', '/api/files/f6/content');
        self::assertStringStartsWith('%PDF-', $this->client->getInternalResponse()->getContent());
    }

    /** @return iterable<string, array{int, string}> sizes as the frontend's fsize() shows them */
    public static function sizes(): iterable
    {
        yield 'bytes' => [1023, '1023 B'];
        yield 'kilobytes, halves rounded up' => [1536, '2 KB'];
        yield 'megabytes with one decimal' => [1992294, '1.9 MB'];
        yield 'a tie rounded up, like toFixed(1)' => [1310720, '1.3 MB'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sizes')]
    public function testTheSizeIsShownLikeTheFrontendDoes(int $bytes, string $size): void
    {
        $body = $this->upload('data.bin', null, ['id' => 'fsz1', 'project' => 'p1'], $bytes);

        self::assertSame([$size, 'other'], [$body['size'], $body['type']]);
    }

    public function testTeammatesDownloadItWithItsNameAndType(): void
    {
        $bytes = random_bytes(3000);
        $this->upload('Hero shot ü.png', $bytes, ['id' => 'fup1', 'project' => 'p1']);
        $this->switchTo('sarah@gr8rstudio.com');

        $this->client->request('GET', '/api/files/fup1/content');

        self::assertResponseStatusCodeSame(200);
        $response = $this->client->getInternalResponse();
        self::assertSame($bytes, $response->getContent());
        self::assertSame('image/png', $response->getHeader('Content-Type'));
        self::assertSame('attachment; filename="Hero shot _.png"; filename*=utf-8\'\'Hero%20shot%20%C3%BC.png', $response->getHeader('Content-Disposition'));
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
    }

    public function testAFileOfAPrivateProjectIsNotFoundForThoseOutsideIt(): void
    {
        $this->switchTo('marcus@gr8rstudio.com');
        $this->upload('contract.pdf', 'terms', ['id' => 'fcp1', 'project' => 'p6', 'task' => 't49']);
        self::assertResponseStatusCodeSame(201);
        $this->switchTo(self::OWNER_EMAIL);

        $this->client->request('GET', '/api/files/fcp1/content');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_found', 'message' => 'There is no such File, or it was deleted.'], $this->lastJson());
        self::assertNull($this->file('fcp1'));
    }

    public function testNobodyUploadsToAPrivateProjectTheyAreNotIn(): void
    {
        $body = $this->upload('peek.txt', 'x', ['id' => 'fcp1', 'project' => 'p6']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'message' => 'You may not change the Files of the Project "p6".'], $body);
    }

    public function testAFileOfMoreThan25MegabytesIsRefused(): void
    {
        $body = $this->upload('raw-footage.zip', null, ['id' => 'fbig1', 'project' => 'p1'], 25 * 1048576 + 1);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'The file is larger than 25 MB, the most a File may be.'], $body);
        self::assertNull($this->file('fbig1'));
    }

    public function testAFileOfExactly25MegabytesIsStored(): void
    {
        if (UploadedFile::getMaxFilesize() < 25 * 1048576) {
            self::markTestSkipped('PHP keeps uploads of up to '.ini_get('upload_max_filesize').' only; run php -d upload_max_filesize=30M -d post_max_size=32M bin/phpunit');
        }
        $body = $this->upload('footage.zip', null, ['id' => 'fbig1', 'project' => 'p1'], 25 * 1048576);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('25.0 MB', $body['size']);
    }

    public function testAnUploadUnderAnIdInUseIsRefusedAndKeepsTheFileThatHasIt(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1']);

        $body = $this->upload('other.txt', 'other', ['id' => 'fup1', 'project' => 'p1']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => 'The id "fup1" is already used.'], $body);
        $this->client->request('GET', '/api/files/fup1/content');
        self::assertSame('hello', $this->client->getInternalResponse()->getContent());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function uploadsWithWrongFields(): iterable
    {
        yield 'no id' => [['project' => 'p1'], 'A File needs an id.'];
        yield 'an id of the wrong form' => [['id' => 'F 1', 'project' => 'p1'], 'The id must be a string matching /^[a-z]{1,3}[a-z0-9-]{1,40}$/.'];
        yield 'an unknown Project' => [['id' => 'fup1', 'project' => 'p99'], 'The File\'s Project "p99" does not exist.'];
        yield 'a Task of another Project' => [['id' => 'fup1', 'project' => 'p1', 'task' => 't49'], 'The File\'s Task "t49" is not in the Project "p1".'];
    }

    /** @param array<string, string> $fields */
    #[\PHPUnit\Framework\Attributes\DataProvider('uploadsWithWrongFields')]
    public function testAnUploadWithWrongFieldsIsRefused(array $fields, string $message): void
    {
        $body = $this->upload('notes.txt', 'hello', $fields);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'message' => $message], $body);
        self::assertNull($this->file('fup1'));
    }

    public function testAFileUploadedToATaskIsOneOfItsAttachments(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1', 'task' => 't4']);

        self::assertSame([['id' => 'fup1', 'name' => 'notes.txt', 'type' => 'doc', 'size' => '5 B', 'by' => 'm1', 'at' => self::NOW]], $this->task('t4')['attachments']);
    }

    public function testATasksAttachmentsAreItsFilesWhateverATaskPutSays(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1', 'task' => 't4']);
        $t4 = $this->task('t4');
        $madeUp = ['id' => 'a9', 'name' => 'ghost.pdf', 'type' => 'pdf', 'size' => '1 KB', 'by' => 'm1', 'at' => self::NOW];

        $this->sync([['op' => 'put', 'type' => 'tasks', 'id' => 't4', 'data' => [...$t4, 'title' => 'Renamed', 'attachments' => [$madeUp]]]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Renamed', $this->task('t4')['title']);
        self::assertSame(['fup1'], array_column($this->task('t4')['attachments'], 'id'));
        self::assertNull($this->file('a9'));
    }

    public function testRenamingAFileThroughTheSyncDerivesItsTypeAgain(): void
    {
        $file = $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1']);

        $body = $this->sync([['op' => 'put', 'type' => 'files', 'id' => 'fup1', 'data' => [...$file, 'name' => 'notes.md.pdf', 'type' => 'doc']]]);

        self::assertSame(['ok' => true], $body);
        self::assertSame(['notes.md.pdf', 'pdf'], [$this->file('fup1')['name'], $this->file('fup1')['type']]);
        $this->client->request('GET', '/api/files/fup1/content');
        self::assertSame('hello', $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('filename=notes.md.pdf', $this->client->getInternalResponse()->getHeader('Content-Disposition'));
    }

    public function testDeletingAFileThroughTheSyncRemovesItAndItsContent(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1']);

        $body = $this->sync([['op' => 'delete', 'type' => 'files', 'id' => 'fup1']]);

        self::assertSame(['ok' => true], $body);
        self::assertNull($this->file('fup1'));
        $this->client->request('GET', '/api/files/fup1/content');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingATaskDeletesItsFilesAndTheirContent(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1', 'task' => 't4']);
        $this->upload('other.txt', 'other', ['id' => 'fup2', 'project' => 'p1']);

        $this->sync([['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->file('fup1'));
        self::assertNotNull($this->file('fup2'));
        $this->client->request('GET', '/api/files/fup1/content');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingAProjectDeletesItsFilesAndTheirContent(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p4', 'task' => 't36']);
        $this->upload('other.txt', 'other', ['id' => 'fup2', 'project' => 'p4']);

        $this->sync([['op' => 'delete', 'type' => 'projects', 'id' => 'p4']]);

        self::assertResponseStatusCodeSame(200);
        foreach (['fup1', 'fup2'] as $id) {
            self::assertNull($this->file($id));
            $this->client->request('GET', "/api/files/$id/content");
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testARefusedBatchKeepsTheFileItDeletedAndItsContent(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1']);

        $this->sync([['op' => 'delete', 'type' => 'files', 'id' => 'fup1'], ['op' => 'put', 'type' => 'nope', 'id' => 'x1', 'data' => ['id' => 'x1']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNotNull($this->file('fup1'));
        $this->client->request('GET', '/api/files/fup1/content');
        self::assertSame('hello', $this->client->getInternalResponse()->getContent());
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function changesOfFilesInvisibleToTheOwner(): iterable
    {
        yield 'rename' => ['put', ['id' => 'fcp1', 'name' => 'mine.pdf']];
        yield 'delete' => ['delete', []];
    }

    /** @param array<string, mixed> $data */
    #[\PHPUnit\Framework\Attributes\DataProvider('changesOfFilesInvisibleToTheOwner')]
    public function testNobodyRenamesOrDeletesAFileOfAPrivateProjectTheyAreNotIn(string $op, array $data): void
    {
        $this->switchTo('marcus@gr8rstudio.com');
        $this->upload('contract.pdf', 'terms', ['id' => 'fcp1', 'project' => 'p6']);
        $this->switchTo(self::OWNER_EMAIL);

        $body = $this->sync([['op' => $op, 'type' => 'files', 'id' => 'fcp1', ...([] === $data ? [] : ['data' => $data])]]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'forbidden', 'op' => 0, 'message' => 'You may not change the File "fcp1".'], $body);
    }

    public function testDuplicatingAFileCopiesItsContentUnderANewId(): void
    {
        $this->upload('notes.txt', 'hello', ['id' => 'fup1', 'project' => 'p1', 'task' => 't4']);
        $this->switchTo('sarah@gr8rstudio.com');

        $body = $this->postJson('/api/files/fup1/copy', ['id' => 'fup2']);

        self::assertResponseStatusCodeSame(201);
        $copy = ['id' => 'fup2', 'project' => 'p1', 'name' => 'notes copy.txt', 'type' => 'doc', 'size' => '5 B', 'by' => 'm2', 'at' => self::NOW, 'task' => 't4'];
        self::assertSame($copy, $body);
        self::assertSame($copy, $this->file('fup2'));
        $this->client->request('GET', '/api/files/fup2/content');
        self::assertSame('hello', $this->client->getInternalResponse()->getContent());
    }

    public function testAFileOfAPrivateProjectCannotBeDuplicatedByThoseOutsideIt(): void
    {
        $this->switchTo('marcus@gr8rstudio.com');
        $this->upload('contract.pdf', 'terms', ['id' => 'fcp1', 'project' => 'p6']);
        $this->switchTo(self::OWNER_EMAIL);

        $body = $this->postJson('/api/files/fcp1/copy', ['id' => 'fcp2']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $body['error']);
    }

    /**
     * @param array<mixed> $ops
     *
     * @return array<mixed>
     */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }

    /**
     * @param string|null           $content the file's bytes, or null for a file of `$sparseBytes` zeros
     * @param array<string, string> $fields
     *
     * @return array<mixed>
     */
    private function upload(string $name, ?string $content, array $fields, int $sparseBytes = 0): array
    {
        $path = tempnam(sys_get_temp_dir(), 'gr8r-upload');
        if (null === $content) {
            // A file of this size without writing its bytes
            $handle = fopen($path, 'w');
            ftruncate($handle, $sparseBytes);
            fclose($handle);
        } else {
            file_put_contents($path, $content);
        }
        $this->client->request('POST', '/api/files', $fields, ['file' => new UploadedFile($path, $name, test: true)]);

        return $this->lastJson();
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** @return array<string, mixed> the Task as /api/bootstrap shows it */
    private function task(string $id): array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['tasks'], null, 'id')[$id];
    }

    /** @return array<string, mixed>|null the File as /api/bootstrap shows it */
    private function file(string $id): ?array
    {
        return array_column($this->getJson('/api/bootstrap')['data']['files'], null, 'id')[$id] ?? null;
    }
}
