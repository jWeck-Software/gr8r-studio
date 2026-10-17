<?php

namespace App\Controller;

use App\Account\ChangePassword;
use App\Account\DeleteWorkspace;
use App\Account\ForgotPassword;
use App\Account\Passwords;
use App\Account\ResendVerification;
use App\Account\ResetPassword;
use App\Account\SignUp;
use App\Account\SignUps;
use App\Account\StartWorkspace;
use App\Account\VerifyEmail;
use App\Account\Workspaces;
use App\Entity\Member;
use App\Entity\User;
use App\Security\SignedIn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountController extends AbstractController
{
    #[Route('/api/signup', name: 'api_signup', methods: ['POST'])]
    public function signUp(#[MapRequestPayload(acceptFormat: 'json')] SignUp $body, SignUps $signUps): Response
    {
        $signUps->signUp(trim($body->name), $body->email, $body->password);

        return new Response(status: 202);
    }

    /** Always 202, so the answer tells nothing about the address. */
    #[Route('/api/verify-email/resend', name: 'api_verify_email_resend', methods: ['POST'])]
    public function resend(#[MapRequestPayload(acceptFormat: 'json')] ResendVerification $body, SignUps $signUps): Response
    {
        $signUps->resendVerification($body->email);

        return new Response(status: 202);
    }

    /** Always 204, so the answer tells nothing about the address. */
    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgotPassword(#[MapRequestPayload(acceptFormat: 'json')] ForgotPassword $body, Passwords $passwords): Response
    {
        $passwords->forgotPassword($body->email);

        return new Response(status: 204);
    }

    /** Choosing a new password with the link signs the User in, with the same body as /api/login. */
    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload(acceptFormat: 'json')] ResetPassword $body, Passwords $passwords, Security $security, SignedIn $signedIn): Response
    {
        $user = $passwords->resetPassword($body->token, $body->password);

        return $security->login($user, 'json_login', 'api') ?? new JsonResponse($signedIn->body($user));
    }

    /** The session it is sent from stays signed in; the User's other sessions end. */
    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    public function changePassword(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] ChangePassword $body, Passwords $passwords): Response
    {
        $passwords->changePassword($user, $body->current, $body->new);

        return new Response(status: 204);
    }

    #[Route('/api/workspace', name: 'api_workspace_delete', methods: ['DELETE'])]
    public function deleteWorkspace(Member $member, #[MapRequestPayload(acceptFormat: 'json')] DeleteWorkspace $body, Workspaces $workspaces): Response
    {
        $workspaces->deleteWorkspace($member, $body->name);

        return new Response(status: 204);
    }

    /** Verifying signs the User in, with the same body as /api/login: `member` is null until they start a Workspace. */
    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verify(#[MapRequestPayload(acceptFormat: 'json')] VerifyEmail $body, SignUps $signUps, Security $security, SignedIn $signedIn): Response
    {
        $user = $signUps->verify($body->token);

        return $security->login($user, 'json_login', 'api') ?? new JsonResponse($signedIn->body($user));
    }

    /**
     * A signed-in User without a Workspace starts one as its Owner; the answer is /api/me's body.
     * No `Member $member` argument: they have none yet (CurrentMemberResolver would refuse).
     */
    #[Route('/api/workspaces', name: 'api_workspaces', methods: ['POST'])]
    public function startWorkspace(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] StartWorkspace $body, Workspaces $workspaces, SignedIn $signedIn): JsonResponse
    {
        $workspaces->startWorkspace($user, trim($body->name), $body->url, $body->c);

        return new JsonResponse($signedIn->body($user), 201);
    }
}
