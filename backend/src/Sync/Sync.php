<?php

namespace App\Sync;

use App\Entity\Member;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Applies a batch of writes from the frontend for one Member, all in one transaction: an
 * ordered list of `put` and `delete` ops on collections (CollectionHandler) and `set` ops on
 * singletons (SingletonHandler). Either every op is applied or, when one is refused, none is.
 */
final readonly class Sync
{
    /** Ids are client-generated (ADR 0003); the frontend's uid() and the seed ids match this. */
    public const string ID_FORMAT = '/^[a-z]{1,3}[a-z0-9-]{1,40}$/';

    /** @var array<string, CollectionHandler|SingletonHandler> */
    private array $handlers;

    /**
     * @param iterable<CollectionHandler> $collections
     * @param iterable<SingletonHandler>  $singletons
     */
    public function __construct(
        private EntityManagerInterface $em,
        #[AutowireIterator(CollectionHandler::class)]
        iterable $collections,
        #[AutowireIterator(SingletonHandler::class)]
        iterable $singletons,
    ) {
        $byType = [];
        foreach ([...$collections, ...$singletons] as $handler) {
            $byType[$handler->type()] = $handler;
        }
        $this->handlers = $byType;
    }

    /**
     * @param list<mixed> $ops the ops of the request body, `{"ops": [...]}` (Batch)
     *
     * @return array<string, array<string, mixed>> what the client has to learn about the records it
     *                                             sent, such as `keys` of new Tasks the server
     *                                             renumbered (ADR 0003); empty when all was stored as sent
     *
     * @throws SyncRejected with the index of the refused op, if any; nothing has been written
     */
    public function apply(Member $actor, array $ops): array
    {
        return $this->em->wrapInTransaction(function () use ($actor, $ops): array {
            $reply = [];
            foreach ($ops as $index => $op) {
                try {
                    $reply = array_replace_recursive($reply, $this->applyOne($actor, $op));
                    $this->em->flush();
                } catch (SyncRejected $rejected) {
                    throw $rejected->atOp($index);
                } catch (UniqueConstraintViolationException) {
                    throw SyncRejected::invalid('The record conflicts with an existing one.')->atOp($index);
                }
            }

            return $reply;
        });
    }

    /** @return array<string, array<string, mixed>> */
    private function applyOne(Member $actor, mixed $op): array
    {
        if (!\is_array($op) || !\is_string($op['op'] ?? null) || !\is_string($op['type'] ?? null)) {
            throw SyncRejected::invalid('Every op needs an "op" and a "type".');
        }
        $handler = $this->handlers[$op['type']] ?? throw SyncRejected::invalid(\sprintf('Unknown type "%s".', $op['type']));
        if ($handler instanceof SingletonHandler) {
            if ('set' !== $op['op']) {
                throw SyncRejected::invalid(\sprintf('"%s" is not an op for %s; use set.', $op['op'], $op['type']));
            }
            if (!\array_key_exists('data', $op)) {
                throw SyncRejected::invalid('A set needs the whole value as "data".');
            }
            $handler->set($actor, $op['data']);

            return [];
        }
        if (!\in_array($op['op'], ['put', 'delete'], true)) {
            throw SyncRejected::invalid(\sprintf('"%s" is not an op for %s; use put or delete.', $op['op'], $op['type']));
        }
        $id = $op['id'] ?? null;
        if (!\is_string($id) || 1 !== preg_match(self::ID_FORMAT, $id)) {
            throw SyncRejected::invalid('The id must be a string matching '.self::ID_FORMAT.'.');
        }

        if ('delete' === $op['op']) {
            $handler->delete($actor, $id);

            return [];
        }
        $data = $op['data'] ?? null;
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw SyncRejected::invalid('A put needs the full record as "data".');
        }
        if (($data['id'] ?? null) !== $id) {
            throw SyncRejected::invalid('The record\'s id must match the op\'s id.');
        }

        return $handler->put($actor, $id, $data);
    }
}
