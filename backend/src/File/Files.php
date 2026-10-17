<?php

namespace App\File;

use App\Access\ProjectAccess;
use App\Entity\File;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Task;
use App\Persistence\Transaction;
use App\Sync\Sync;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;

/**
 * Files and their content: the one place that knows where a File's blob lives
 * (`<storage dir>/<Workspace id>/<File id>`, outside public/). A File's record and its blob come
 * and go together: an upload whose record can't be stored leaves no blob behind, and a removed
 * File's blob goes only once the transaction that removed it has committed.
 */
final readonly class Files
{
    /** 25 MiB, the most a File may be: the frontend shows it as "25 MB". */
    public const int MAX_BYTES = 25 * 1048576;

    public function __construct(
        private EntityManagerInterface $em,
        private Transaction $transaction,
        private ProjectAccess $access,
        private ClockInterface $clock,
        private Filesystem $filesystem,
        #[Autowire('%env(resolve:FILE_STORAGE_DIR)%')]
        private string $storageDir,
    ) {
    }

    /**
     * Stores an upload as a new File of the Project (and Task), uploaded by the actor now; its
     * type and size are the server's.
     *
     * @return array<string, mixed> the File in the frontend's shape
     *
     * @throws FileRefused
     */
    public function store(Member $actor, UploadedFile $upload, string $id, string $projectId, ?string $taskId): array
    {
        if (!$upload->isValid()) {
            // Over upload_max_filesize, PHP keeps none of it; set it above 25 MB (backend/README.md)
            throw \in_array($upload->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true) ? self::tooLarge() : FileRefused::invalid('The upload did not arrive in one piece. Please try again.');
        }
        if ($upload->getSize() > self::MAX_BYTES) {
            throw self::tooLarge();
        }
        $name = trim($upload->getClientOriginalName());
        if ('' === $name || mb_strlen($name) > 255) {
            throw FileRefused::invalid('A File needs a name of at most 255 characters.');
        }
        $this->assertNewId($id);

        return $this->create(function () use ($actor, $upload, $id, $projectId, $taskId, $name): File {
            $ws = $actor->getWorkspace();
            $project = $this->em->find(Project::class, $projectId);
            if (null === $project || $project->getWorkspace() !== $ws) {
                throw FileRefused::invalid(\sprintf('The File\'s Project "%s" does not exist.', $projectId));
            }
            $this->authorize($actor, $project);
            $task = null;
            if (null !== $taskId && '' !== $taskId) {
                $task = $this->em->find(Task::class, $taskId);
                if (null === $task || $task->getProject() !== $project) {
                    throw FileRefused::invalid(\sprintf('The File\'s Task "%s" is not in the Project "%s".', $taskId, $projectId));
                }
            }

            return new File($id, $ws, $project, $task, $name, File::sizeOf((int) $upload->getSize()), $actor->getId(), $this->now());
        }, static fn (string $path) => $upload->move(\dirname($path), basename($path)));
    }

    /**
     * The File's content for a Member who sees its Project, to save under its name. Served with
     * the type its name says, never sniffed, and sandboxed: an uploaded HTML or SVG file runs no
     * script in the app's origin.
     *
     * @throws FileRefused 404 for a File the Member doesn't see, as for one that doesn't exist
     */
    public function download(Member $actor, string $id): BinaryFileResponse
    {
        $file = $this->em->find(File::class, $id);
        if (null === $file || !$this->access->sees($actor, $file->getProject()) || !is_file($this->path($file))) {
            throw FileRefused::notFound();
        }
        $name = $file->getName();
        $dot = strrpos($name, '.');
        $type = false === $dot ? null : (MimeTypes::getDefault()->getMimeTypes(strtolower(substr($name, $dot + 1)))[0] ?? null);
        $response = new BinaryFileResponse($this->path($file), headers: [
            'Content-Type' => $type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $safe = str_replace(['/', '\\'], '-', $name);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $safe, preg_replace('/[^\x20-\x7e]|%/u', '_', $safe));

        return $response;
    }

    /**
     * Duplicates a File the actor may change, content included: the copy is named like the
     * frontend's "Duplicate" (" copy" before the extension) and is the actor's, made now.
     *
     * @return array<string, mixed> the copy in the frontend's shape
     *
     * @throws FileRefused
     */
    public function copy(Member $actor, string $sourceId, string $id): array
    {
        $source = $this->em->find(File::class, $sourceId);
        if (null === $source || !$this->access->sees($actor, $source->getProject())) {
            throw FileRefused::notFound();
        }
        $this->authorize($actor, $source->getProject());
        $this->assertNewId($id);

        return $this->create(
            fn (): File => $source->copy($id, $actor->getId(), $this->now()),
            fn (string $path) => $this->filesystem->copy($this->path($source), $path),
        );
    }

    /**
     * Removes the File; its content goes once the running transaction (Transaction::run()) has
     * committed, so a refused batch keeps both.
     */
    public function remove(File $file): void
    {
        $path = $this->path($file);
        $this->em->remove($file);
        $this->transaction->afterCommit(fn () => $this->filesystem->remove($path));
    }

    /** Removes, like remove(), every File of the Project or attached to the Task. */
    public function removeAllOf(Project|Task $owner): void
    {
        foreach ($this->em->getRepository(File::class)->findBy([$owner instanceof Task ? 'task' : 'project' => $owner]) as $file) {
            $this->remove($file);
        }
    }

    /** Moves the Files attached to the Task into its Project, after the Task moved. */
    public function followTask(Task $task): void
    {
        foreach ($this->em->getRepository(File::class)->findBy(['task' => $task]) as $file) {
            $file->moveTo($task->getProject());
        }
    }

    /** Stores the File's content as it is, outside any transaction: for the demo seed. */
    public function putContent(File $file, string $bytes): void
    {
        $this->filesystem->dumpFile($this->path($file), $bytes);
    }

    /** Removes the content of every File of a Workspace that is being deleted. */
    public function forgetWorkspace(int $workspaceId): void
    {
        $this->filesystem->remove($this->storageDir.'/'.$workspaceId);
    }

    /**
     * Stores the File that `$file` makes, then has `$write` put its content at its path, in one
     * transaction: when either fails, nothing is stored and the content written is removed again.
     *
     * @param \Closure(): File        $file
     * @param \Closure(string): mixed $write
     *
     * @return array<string, mixed> the File in the frontend's shape
     */
    private function create(\Closure $file, \Closure $write): array
    {
        $written = null;
        try {
            return $this->transaction->run(function () use ($file, $write, &$written): array {
                $created = $file();
                $this->em->persist($created);
                $this->em->flush();
                $written = $this->path($created);
                $write($written);

                return $created->toRecord();
            });
        } catch (\Throwable $e) {
            if (null !== $written) {
                $this->filesystem->remove($written);
            }
            throw $e;
        }
    }

    private function assertNewId(string $id): void
    {
        if (1 !== preg_match(Sync::ID_FORMAT, $id)) {
            throw FileRefused::invalid('The id must be a string matching '.Sync::ID_FORMAT.'.');
        }
        if (null !== $this->em->find(File::class, $id)) {
            throw FileRefused::invalid(\sprintf('The id "%s" is already used.', $id));
        }
    }

    private function authorize(Member $actor, Project $project): void
    {
        if (!$this->access->mayChange($actor, $project)) {
            throw FileRefused::forbidden(\sprintf('You may not change the Files of the Project "%s".', $project->getId()));
        }
    }

    private function path(File $file): string
    {
        return $this->storageDir.'/'.$file->getWorkspace()->getId().'/'.$file->getId();
    }

    private function now(): int
    {
        return (int) $this->clock->now()->format('Uv');
    }

    private static function tooLarge(): FileRefused
    {
        return FileRefused::invalid('The file is larger than 25 MB, the most a File may be.');
    }
}
