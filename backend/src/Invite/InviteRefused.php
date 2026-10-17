<?php

namespace App\Invite;

/** An Invite link that does not (or no longer) lead into the Workspace, with the reason to show. */
final class InviteRefused extends \RuntimeException
{
    private function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** No open Invite has this token: it never existed, was accepted already or was replaced by a newer one. */
    public static function unknown(): self
    {
        return new self(404, 'not_found', 'This invite link is not valid any more. It may have been used already or replaced by a newer one.');
    }

    /** The link is older than a week. */
    public static function expired(): self
    {
        return new self(410, 'expired', 'This invite has expired. Ask an Owner or Admin of the Workspace to send you a new one.');
    }

    /** The password chosen to accept it is too weak (PasswordPolicy). */
    public static function weakPassword(string $message): self
    {
        return new self(422, 'invalid', $message);
    }

    /** The email address has a User already, and the password given is not theirs. */
    public static function notTheirPassword(string $email): self
    {
        return new self(401, 'invalid_credentials', \sprintf('%s already has an account: enter its password to join.', $email));
    }

    /** The email address's User is a Member of another Workspace; switching Workspaces is out of scope. */
    public static function inAnotherWorkspace(string $email): self
    {
        return new self(409, 'conflict', \sprintf('%s is a Member of another Workspace already, and an account belongs to one Workspace for now.', $email));
    }

    /** @return array{error: string, message: string} */
    public function body(): array
    {
        return ['error' => $this->error, 'message' => $this->getMessage()];
    }
}
