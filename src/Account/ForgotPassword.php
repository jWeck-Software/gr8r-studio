<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/password/forgot: the address to send a link for a new password to. */
final readonly class ForgotPassword
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter your email address.', normalizer: 'trim')]
        public string $email = '',
    ) {
    }
}
