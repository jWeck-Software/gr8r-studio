<?php

namespace App\Invite;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/invites/{token}/accept: the name the invited person goes by, and a password. */
final readonly class AcceptInvite
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter your name.', normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'Your name can have at most 120 characters.')]
        public string $name = '',
        #[Assert\NotBlank(message: 'Enter a password.')]
        public string $password = '',
    ) {
    }
}
