<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The rate limits of the endpoints that check a password or send an email, in one place: which
 * route counts against which limiter (config/packages/rate_limiter.yaml), by what key. Past a limit
 * the request answers 429 `too_many_requests` with Retry-After, before the firewall or the
 * controller sees it, so a refused sign-in checks no password.
 *
 * Runs after the router (priority 32), which names the route, and before the firewall (8), which
 * handles the sign-in. The email address is read from the JSON body here, before the firewall's
 * json_login and #[MapRequestPayload] read it.
 */
final readonly class RateLimits
{
    /**
     * For each route the limiters it counts against, each with the parts of its key: the client's
     * IP, the JSON body's email address, or a route placeholder. Every key also names the route,
     * so the endpoints' limits are apart.
     */
    private const array ROUTES = [
        'api_login' => ['sign_in' => ['email', 'ip'], 'ip' => ['ip']],
        'api_signup' => ['email' => ['email'], 'ip' => ['ip']],
        'api_verify_email' => ['ip' => ['ip']],
        'api_verify_email_resend' => ['email' => ['email'], 'ip' => ['ip']],
        'api_invite_accept' => ['link' => ['token'], 'ip' => ['ip']],
        'api_member_invite' => ['email' => ['invited'], 'ip' => ['ip']],
        'api_password_forgot' => ['email' => ['email'], 'ip' => ['ip']],
        'api_password_reset' => ['ip' => ['ip']],
        'api_me_password' => ['ip' => ['ip']],
    ];

    /** @param ServiceLocator<RateLimiterFactoryInterface> $limiters */
    public function __construct(
        #[AutowireLocator([
            'sign_in' => new Autowire(service: 'limiter.sign_in'),
            'email' => new Autowire(service: 'limiter.email'),
            'link' => new Autowire(service: 'limiter.link'),
            'ip' => new Autowire(service: 'limiter.ip'),
        ])]
        private ServiceLocator $limiters,
    ) {
    }

    #[AsEventListener(priority: 20)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $retryAfter = null;
        foreach (self::ROUTES[$route] ?? [] as $limiter => $parts) {
            $limit = $this->limiters->get($limiter)->create($this->key($request, $route, $parts))->consume();
            if (!$limit->isAccepted()) {
                $retryAfter = max($retryAfter ?? 0, $limit->getRetryAfter()->getTimestamp() - time());
            }
        }
        if (null !== $retryAfter) {
            $seconds = max(1, $retryAfter);
            $event->setResponse(new JsonResponse(
                ['error' => 'too_many_requests', 'message' => \sprintf('Too many attempts. Try again in %s.', $seconds > 90 ? ceil($seconds / 60).' minutes' : $seconds.' seconds')],
                429,
                ['Retry-After' => (string) $seconds],
            ));
        }
    }

    /** A successful sign-in starts its count over, so only wrong passwords add up. */
    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ('api_login' === $request->attributes->get('_route')) {
            $this->limiters->get('sign_in')->create($this->key($request, 'api_login', self::ROUTES['api_login']['sign_in']))->reset();
        }
    }

    /** @param list<string> $parts */
    private function key(Request $request, string $route, array $parts): string
    {
        $key = [$route];
        foreach ($parts as $part) {
            $key[] = match ($part) {
                'ip' => (string) $request->getClientIp(),
                'email' => self::email($request),
                default => (string) $request->attributes->get($part),
            };
        }

        return implode('|', $key);
    }

    /** The email address of the JSON body, in lower case; empty when there is none. */
    private static function email(Request $request): string
    {
        try {
            $email = $request->toArray()['email'] ?? '';
        } catch (JsonException) {
            return '';
        }

        return \is_string($email) ? mb_strtolower(trim($email)) : '';
    }
}
