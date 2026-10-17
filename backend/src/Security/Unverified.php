<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * The User signing in has not verified their email address yet: 403 `unverified` (JsonAuthResponder).
 * Raised only once the password is known to be right, so nobody else learns the address has an account.
 */
final class Unverified extends CustomUserMessageAccountStatusException
{
    public const string ERROR = 'unverified';

    public function __construct()
    {
        parent::__construct('Verify your email address first: open the link we emailed you, or send yourself a new one.');
    }
}
