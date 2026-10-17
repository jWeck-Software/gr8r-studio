<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/verify-email: the token of the link in the verification email. */
final readonly class VerifyEmail
{
    public function __construct(
        #[Assert\NotBlank(message: 'Send the token of the verification link.')]
        public string $token = '',
    ) {
    }
}
