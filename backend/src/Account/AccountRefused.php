<?php

namespace App\Account;

/** What the server refuses about signing up, verifying or starting a Workspace, with the reason to show. */
final class AccountRefused extends \RuntimeException
{
    private function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** No open verification link has this token: it never existed, was used already or was replaced by a newer one. */
    public static function unknownLink(): self
    {
        return new self(404, 'not_found', 'This verification link is not valid any more. It may have been used already or replaced by a newer one.');
    }

    /** The verification link is older than 24 hours. */
    public static function expiredLink(): self
    {
        return new self(410, 'expired', 'This verification link has expired. Sign in to send yourself a new one.');
    }

    /** The password chosen is too weak (PasswordPolicy). */
    public static function weakPassword(string $message): self
    {
        return new self(422, 'invalid', $message);
    }

    /** Another Workspace has this URL. */
    public static function urlTaken(string $url): self
    {
        return new self(422, 'invalid', \sprintf('The Workspace URL "%s" is taken.', $url));
    }

    /** Starting a Workspace as a Member of one; switching Workspaces is out of scope. */
    public static function hasWorkspace(): self
    {
        return new self(409, 'conflict', 'You are a Member of a Workspace already, and an account belongs to one Workspace for now.');
    }

    /** @return array{error: string, message: string} */
    public function body(): array
    {
        return ['error' => $this->error, 'message' => $this->getMessage()];
    }
}
