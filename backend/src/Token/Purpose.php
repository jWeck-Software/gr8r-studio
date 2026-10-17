<?php

namespace App\Token;

/** What a one-time link is for, and how long it works. */
enum Purpose: string
{
    /** Joining a Workspace as the invited Member (the subject is the Member's id). */
    case Invite = 'invite';
    /** Verifying the email address of a User who signed up (the subject is the User's id). */
    case VerifyEmail = 'verify_email';

    /** How long a link works after it was issued, as a DateTimeImmutable::modify() offset. */
    public function validFor(): string
    {
        return match ($this) {
            self::Invite => '+7 days',
            self::VerifyEmail => '+24 hours',
        };
    }
}
