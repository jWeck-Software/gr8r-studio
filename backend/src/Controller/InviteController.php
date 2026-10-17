<?php

namespace App\Controller;

use App\Entity\Member;
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
    /**
     * Owners and Admins send an invited Member a new link; the old one stops working. The route
     * says `{invited}`, not `{id}`: Doctrine would resolve `Member $member` from an `{id}`.
     */
    #[Route('/api/members/{invited}/invite', name: 'api_member_invite', methods: ['POST'])]
    public function resend(string $invited, Member $member, Invites $invites): Response
    {
        try {
            $invites->resend($member, $invited);
        } catch (InviteRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return new Response(status: 204);
    }

    #[Route('/api/invites/{token}', name: 'api_invite', methods: ['GET'])]
    public function show(string $token, Invites $invites): JsonResponse
    {
        try {
            return new JsonResponse($invites->describe($token));
        } catch (InviteRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }
    }

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
