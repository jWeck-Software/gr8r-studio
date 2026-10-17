<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\MemberRepository;

/** The body of a successful sign-in and of GET /api/me: who the User is and which Member they act as. */
final readonly class SignedIn
{
    public function __construct(private MemberRepository $members)
    {
    }

    /** @return array{user: array{email: string, name: string}, member: ?string} */
    public function body(User $user): array
    {
        return [
            'user' => ['email' => $user->getEmail(), 'name' => $user->getName()],
            'member' => $this->members->forUser($user)?->getId(),
        ];
    }
}
