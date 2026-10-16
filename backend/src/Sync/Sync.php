<?php

namespace App\Sync;

use App\Entity\Member;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Applies a batch of writes from the frontend for one Member: an ordered list of
 * `put` and `delete` ops per collection, all in one transaction. Either every op is
 * applied or, when one is refused, none is.
 */
final readonly class Sync
{
    /** Ids are client-generated (ADR 0003); the frontend's uid() and the seed ids match this. */
    public const string ID_FORMAT = '/^[a-z]{1,3}[a-z0-9-]{1,40}$/';

    /** @var array<string, CollectionHandler> */
    private array $handlers;

    /** @param iterable<CollectionHandler> $handlers */
    public function __construct(
        private EntityManagerInterface $em,
        #[AutowireIterator(CollectionHandler::class)]
        iterable $handlers,
    ) {
        $byType = [];
        foreach ($handlers as $handler) {
            $byType[$handler->type()] = $handler;
        }
        $this->handlers = $byType;
    }

    /**
     * @param mixed $batch the decoded request body, `{"ops": [...]}`
     *
     * @throws SyncRejected with the index of the refused op, if any; nothing has been written
     */
    public function apply(Member $actor, mixed $batch): void
    {
        if (!\is_array($batch) || !\is_array($batch['ops'] ?? null) || !array_is_list($batch['ops'])) {
            throw SyncRejected::invalid('The body must be {"ops": [...]}.');
        }

        $this->em->wrapInTransaction(function () use ($actor, $batch): void {
            foreach ($batch['ops'] as $index => $op) {
                try {
                    $this->applyOne($actor, $op);
                    $this->em->flush();
                } catch (SyncRejected $rejected) {
                    throw $rejected->atOp($index);
                } catch (UniqueConstraintViolationException) {
                    throw SyncRejected::invalid('The record conflicts with an existing one.')->atOp($index);
                }
            }
        });
    }

    private function applyOne(Member $actor, mixed $op): void
    {
        if (!\is_array($op) || !\is_string($op['op'] ?? null) || !\is_string($op['type'] ?? null)) {
            throw SyncRejected::invalid('Every op needs an "op" and a "type".');
        }
        $handler = $this->handlers[$op['type']] ?? throw SyncRejected::invalid(\sprintf('Unknown type "%s".', $op['type']));
        if (!\in_array($op['op'], ['put', 'delete'], true)) {
            throw SyncRejected::invalid(\sprintf('"%s" is not an op for %s; use put or delete.', $op['op'], $op['type']));
        }
        $id = $op['id'] ?? null;
        if (!\is_string($id) || 1 !== preg_match(self::ID_FORMAT, $id)) {
            throw SyncRejected::invalid('The id must be a string matching '.self::ID_FORMAT.'.');
        }

        if ('delete' === $op['op']) {
            $handler->delete($actor, $id);

            return;
        }
        $data = $op['data'] ?? null;
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw SyncRejected::invalid('A put needs the full record as "data".');
        }
        if (($data['id'] ?? null) !== $id) {
            throw SyncRejected::invalid('The record\'s id must match the op\'s id.');
        }
        $handler->put($actor, $id, $data);
    }
}
