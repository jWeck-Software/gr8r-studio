<?php

namespace App\Account;

use App\Http\Refused;

/** What the server refuses about signing up, verifying, passwords, or starting or deleting a Workspace, with the reason to show. */
final class AccountRefused extends Refused
{
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

    /** No open password reset link has this token: it never existed, was used already or was replaced by a newer one. */
    public static function unknownResetLink(): self
    {
        return new self(404, 'not_found', 'This password reset link is not valid any more. It may have been used already or replaced by a newer one.');
    }

    /** The password reset link is older than 30 minutes. */
    public static function expiredResetLink(): self
    {
        return new self(410, 'expired', 'This password reset link has expired. Ask for a new one from the sign-in page.');
    }

    /** Changing the password with a current one that is not the User's. */
    public static function wrongPassword(): self
    {
        return new self(401, 'invalid_credentials', 'Your current password is incorrect.');
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

    /** The actor may not do this to the Workspace (WorkspaceAccess). */
    public static function forbidden(string $message): self
    {
        return new self(403, 'forbidden', $message);
    }

    /** Deleting the Workspace is confirmed with its name, exactly. */
    public static function notTheName(string $name): self
    {
        return new self(422, 'invalid', \sprintf('Type the Workspace\'s name, "%s", to delete it.', $name));
    }

    /** Starting a Workspace as a Member of one; switching Workspaces is out of scope. */
    public static function hasWorkspace(): self
    {
        return new self(409, 'conflict', 'You are a Member of a Workspace already, and an account belongs to one Workspace for now.');
    }
}
