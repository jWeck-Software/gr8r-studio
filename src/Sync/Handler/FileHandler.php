<?php

namespace App\Sync\Handler;

use App\Access\ProjectAccess;
use App\Entity\File;
use App\Entity\Member;
use App\File\Files;
use App\Sync\CollectionHandler;
use App\Sync\Fields;
use App\Sync\SyncRejected;
use App\Sync\WorkspaceRecords;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Files through the sync: a put renames one, a delete removes it and its content. A File is
 * created only by an upload (POST /api/files) or a copy, never by a put.
 */
final readonly class FileHandler implements CollectionHandler
{
    public function __construct(
        private ProjectAccess $access,
        private Files $files,
        private WorkspaceRecords $records,
        private EntityManagerInterface $em,
    ) {
    }

    public function type(): string
    {
        return 'files';
    }

    /** Only the name is the client's; type, size, uploader and time are the server's. */
    public function put(Member $actor, string $id, array $data): array
    {
        $name = trim((new Fields($data, 'File'))->nonEmptyString('name', 255));
        $file = $this->records->find(File::class, $actor, $id);
        if (null === $file || $file->getName() === $name) {
            return [];
        }
        $this->authorize($actor, $file);
        $file->rename($name);

        return [];
    }

    public function delete(Member $actor, string $id): void
    {
        // Another Workspace's id is as unknown as one nobody uses: deleting it is a no-op.
        $file = $this->em->find(File::class, $id);
        if (null !== $file && $file->getWorkspace() === $actor->getWorkspace()) {
            $this->authorize($actor, $file);
            $this->files->remove($file);
        }
    }

    private function authorize(Member $actor, File $file): void
    {
        if (!$this->access->mayChange($actor, $file->getProject())) {
            throw SyncRejected::forbidden(\sprintf('You may not change the File "%s".', $file->getId()));
        }
    }
}
