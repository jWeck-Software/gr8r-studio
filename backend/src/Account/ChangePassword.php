<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/me/password: the signed-in User's current password, and the new one. */
final readonly class ChangePassword
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter your current password.')]
        public string $current = '',
        #[Assert\NotBlank(message: 'Choose a new password.')]
        public string $new = '',
    ) {
    }
}
