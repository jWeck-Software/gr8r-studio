<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/** Answers sign-in, sign-out and missing sessions with JSON instead of redirects. */
final readonly class JsonAuthResponder implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function __construct(private SignedIn $signedIn)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        \assert($user instanceof User);

        return new JsonResponse($this->signedIn->body($user));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof Unverified) {
            return new JsonResponse(['error' => Unverified::ERROR, 'message' => $exception->getMessageKey()], 403);
        }

        return new JsonResponse(['error' => 'invalid_credentials', 'message' => 'Email or password is incorrect.'], 401);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return self::unauthenticated();
    }

    /** Runs before Symfony's default listener, which would redirect. */
    #[AsEventListener(priority: 100)]
    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(null === $event->getToken() ? self::unauthenticated() : new Response(status: 204));
    }

    private static function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['error' => 'unauthenticated', 'message' => 'Sign in to continue.'], 401);
    }
}
