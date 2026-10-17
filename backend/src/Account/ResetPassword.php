<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/password/reset: the token of the link in the email, and the new password. */
final readonly class ResetPassword
{
    public function __construct(
        #[Assert\NotBlank(message: 'Send the token of the password reset link.')]
        public string $token = '',
        #[Assert\NotBlank(message: 'Choose a new password.')]
        public string $password = '',
    ) {
    }
}
