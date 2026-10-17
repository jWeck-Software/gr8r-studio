<?php

namespace App\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A database transaction with work that waits for its commit, such as an email about what it
 * stored: run() commits the work and only then runs what afterCommit() deferred; when the work
 * throws, everything is rolled back and nothing deferred runs.
 */
final class Transaction implements ResetInterface
{
    /** @var list<\Closure(): void> */
    private array $deferred = [];
    private bool $running = false;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $work
     *
     * @return T
     */
    public function run(\Closure $work): mixed
    {
        if ($this->running) {
            throw new \LogicException('Transactions do not nest.');
        }
        $this->running = true;
        try {
            $result = $this->em->wrapInTransaction($work);
            $deferred = $this->deferred;
        } finally {
            $this->reset();
        }
        foreach ($deferred as $then) {
            $then();
        }

        return $result;
    }

    /** Runs once the running transaction has committed, never when it is rolled back. */
    public function afterCommit(\Closure $then): void
    {
        if (!$this->running) {
            throw new \LogicException('afterCommit() needs a running transaction (run()).');
        }
        $this->deferred[] = $then;
    }

    public function reset(): void
    {
        $this->deferred = [];
        $this->running = false;
    }
}
