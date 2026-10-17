<?php

namespace App\Security;

/** What a new password needs, as the app asks for it: at least 10 characters, including a number or symbol. */
final class PasswordPolicy
{
    /** Why the password is too weak to be chosen, or null when it will do. */
    public static function whyWeak(string $password): ?string
    {
        // The app's check: /[0-9\W]/, where "_" counts as a letter.
        return mb_strlen($password) < 10 || 1 !== preg_match('/[0-9\W]/u', $password)
            ? 'Use at least 10 characters, including a number or symbol.'
            : null;
    }
}
