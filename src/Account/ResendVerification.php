<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/verify-email/resend: the address that waits for its verification link. */
final readonly class ResendVerification
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter your email address.', normalizer: 'trim')]
        public string $email = '',
    ) {
    }
}
