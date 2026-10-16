<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\SignedIn;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AuthController
{
    /** Reached only when json_login did not handle the request, e.g. a body that is not JSON. */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return new JsonResponse(['error' => 'invalid', 'message' => 'Send email and password as JSON.'], 400);
    }

    /** Handled by the firewall's logout listener; the route only has to exist. */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles sign-out.');
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user, SignedIn $signedIn): JsonResponse
    {
        return new JsonResponse($signedIn->body($user));
    }
}
