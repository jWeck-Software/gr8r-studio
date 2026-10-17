<?php

namespace App\Invite;

use App\Http\Refused;

/** What the server refuses about an Invite, such as a link that does not lead into the Workspace (any more), with the reason to show. */
final class InviteRefused extends Refused
{
    /** No open Invite has this token: it never existed, was accepted already or was replaced by a newer one. */
    public static function unknown(): self
    {
        return new self(404, 'not_found', 'This invite link is not valid any more. It may have been used already or replaced by a newer one.');
    }

    /** Sending an Invite again to someone the Workspace does not have. */
    public static function noSuchMember(): self
    {
        return new self(404, 'not_found', 'This Workspace has no such Member.');
    }

    /** The actor may not invite this Member (WorkspaceAccess). */
    public static function forbidden(string $message): self
    {
        return new self(403, 'forbidden', $message);
    }

    /** Sending an Invite again to a Member who accepted it. */
    public static function joinedAlready(string $name): self
    {
        return new self(422, 'invalid', \sprintf('%s has joined already.', $name));
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
}
