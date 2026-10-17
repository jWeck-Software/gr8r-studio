<?php

namespace App\Account;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\Workspace;
use App\File\Files;
use App\Persistence\Transaction;
use App\Repository\MemberRepository;
use App\Token\Purpose;
use App\Token\Tokens;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A User's own Workspace: starting one as its Owner, and deleting it with everything in it.
 */
final class Workspaces
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaction $transaction,
        private readonly MemberRepository $members,
        private readonly WorkspaceAccess $access,
        private readonly Tokens $tokens,
        private readonly Files $files,
    ) {
    }

    /**
     * Starts a new Workspace with this name, URL slug and colour, whose Owner the User is, with the
     * default Teams and notification settings (WorkspaceDefaults) and no Projects yet.
     *
     * @throws AccountRefused when the User is a Member of a Workspace already, or the URL is taken
     */
    public function startWorkspace(User $user, string $name, string $url, string $c): Member
    {
        try {
            return $this->start($user, $name, $url, $c);
        } catch (UniqueConstraintViolationException) {
            // Another Workspace took the URL at the same moment.
            throw AccountRefused::urlTaken($url);
        }
    }

    private function start(User $user, string $name, string $url, string $c): Member
    {
        return $this->transaction->run(function () use ($user, $name, $url, $c): Member {
            if (null !== $this->members->forUser($user)) {
                throw AccountRefused::hasWorkspace();
            }
            if (null !== $this->em->getRepository(Workspace::class)->findOneBy(['url' => $url])) {
                throw AccountRefused::urlTaken($url);
            }
            $ws = new Workspace($name, $url, $c);
            $owner = Member::owner(self::newId('m'), $ws, $user);
            $owner->keepPersonal('notifPrefs', WorkspaceDefaults::NOTIF_PREFS);
            $this->em->persist($ws);
            $this->em->persist($owner);
            // Team ids are unique across Workspaces, so the new one's are its own; numbered, so that
            // they keep their order (Snapshot lists records by id).
            $stem = self::newId('tm');
            foreach (array_values(WorkspaceDefaults::TEAMS) as $i => $record) {
                $team = new Team($stem.'-'.($i + 1), $ws);
                $team->update($record);
                $this->em->persist($team);
            }
            $this->em->flush();

            return $owner;
        });
    }

    /**
     * Deletes the actor's Workspace with everything in it, when they are its Owner and confirm with
     * its exact name. Its Members' Users stay, without a Workspace: their next request is
     * 403 `no_workspace`, as for a removed Member.
     *
     * @throws AccountRefused when the actor is no Owner, or the name is not the Workspace's
     */
    public function deleteWorkspace(Member $actor, string $name): void
    {
        $refusal = $this->access->whyNotDeleteWorkspace($actor);
        if (null !== $refusal) {
            throw AccountRefused::forbidden($refusal);
        }
        if ($name !== $actor->getWorkspace()->getName()) {
            throw AccountRefused::notTheName($actor->getWorkspace()->getName());
        }
        $this->transaction->run(function () use ($actor): void {
            $ws = $actor->getWorkspace();
            $id = (int) $ws->getId();
            // Everything else of the Workspace goes with it (ON DELETE CASCADE); the links of its open Invites name only a Member's id.
            foreach ($this->em->getRepository(Member::class)->findBy(['workspace' => $ws]) as $member) {
                $this->tokens->revoke(Purpose::Invite, $member->getId());
            }
            $this->em->remove($ws);
            $this->em->flush();
            $this->transaction->afterCommit(fn () => $this->files->forgetWorkspace($id));
        });
    }

    /** A fresh id for a record the server makes, shaped like the frontend's uid(): a prefix and random letters and digits. */
    private static function newId(string $prefix): string
    {
        return $prefix.bin2hex(random_bytes(6));
    }
}
