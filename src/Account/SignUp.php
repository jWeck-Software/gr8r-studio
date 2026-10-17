<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/signup: who signs up, and the password they choose. */
final readonly class SignUp
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter your name.', normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'Your name can have at most 120 characters.')]
        public string $name = '',
        #[Assert\NotBlank(message: 'Enter your email address.', normalizer: 'trim')]
        #[Assert\Email(message: 'Enter a valid email address, like name@company.com.', normalizer: 'trim')]
        #[Assert\Length(max: 180, maxMessage: 'An email address can have at most 180 characters.')]
        public string $email = '',
        #[Assert\NotBlank(message: 'Enter a password.')]
        public string $password = '',
    ) {
    }
}
