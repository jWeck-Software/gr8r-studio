<?php

namespace App\Controller;

use App\Account\AccountRefused;
use App\Account\Accounts;
use App\Account\ForgotPassword;
use App\Account\ResendVerification;
use App\Account\ResetPassword;
use App\Account\SignUp;
use App\Account\StartWorkspace;
use App\Entity\User;
use App\Account\VerifyEmail;
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
    public function signUp(#[MapRequestPayload(acceptFormat: 'json')] SignUp $body, Accounts $accounts): Response
    {
        try {
            $accounts->signUp(trim($body->name), $body->email, $body->password);
        } catch (AccountRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return new Response(status: 202);
    }

    /** Always 202, so the answer tells nothing about the address. */
    #[Route('/api/verify-email/resend', name: 'api_verify_email_resend', methods: ['POST'])]
    public function resend(#[MapRequestPayload(acceptFormat: 'json')] ResendVerification $body, Accounts $accounts): Response
    {
        $accounts->resendVerification($body->email);

        return new Response(status: 202);
    }

    /** Always 204, so the answer tells nothing about the address. */
    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgotPassword(#[MapRequestPayload(acceptFormat: 'json')] ForgotPassword $body, Accounts $accounts): Response
    {
        $accounts->forgotPassword($body->email);

        return new Response(status: 204);
    }

    /** Choosing a new password with the link signs the User in, with the same body as /api/login. */
    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload(acceptFormat: 'json')] ResetPassword $body, Accounts $accounts, Security $security, SignedIn $signedIn): Response
    {
        try {
            $user = $accounts->resetPassword($body->token, $body->password);
        } catch (AccountRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return $security->login($user, 'json_login', 'api') ?? new JsonResponse($signedIn->body($user));
    }

    /** Verifying signs the User in, with the same body as /api/login: `member` is null until they start a Workspace. */
    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verify(#[MapRequestPayload(acceptFormat: 'json')] VerifyEmail $body, Accounts $accounts, Security $security, SignedIn $signedIn): Response
    {
        try {
            $user = $accounts->verify($body->token);
        } catch (AccountRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return $security->login($user, 'json_login', 'api') ?? new JsonResponse($signedIn->body($user));
    }

    /**
     * A signed-in User without a Workspace starts one as its Owner; the answer is /api/me's body.
     * No `Member $member` argument: they have none yet (CurrentMemberResolver would refuse).
     */
    #[Route('/api/workspaces', name: 'api_workspaces', methods: ['POST'])]
    public function startWorkspace(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] StartWorkspace $body, Accounts $accounts, SignedIn $signedIn): JsonResponse
    {
        try {
            $accounts->startWorkspace($user, trim($body->name), $body->url, $body->c);
        } catch (AccountRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }

        return new JsonResponse($signedIn->body($user), 201);
    }
}
