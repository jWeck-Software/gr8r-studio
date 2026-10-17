<?php

namespace App\Controller;

use App\Invite\AcceptInvite;
use App\Invite\InviteRefused;
use App\Invite\Invites;
use App\Security\SignedIn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class InviteController extends AbstractController
{
    /** Accepting signs the person in as the Member they were invited as, with the same body as /api/login. */
    #[Route('/api/invites/{token}/accept', name: 'api_invite_accept', methods: ['POST'])]
    public function accept(string $token, #[MapRequestPayload(acceptFormat: 'json')] AcceptInvite $body, Invites $invites, Security $security, SignedIn $signedIn): Response
    {
        try {
            $user = $invites->accept($token, trim($body->name), $body->password);
        } catch (InviteRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return $security->login($user, 'json_login', 'api') ?? new JsonResponse($signedIn->body($user));
    }
}
