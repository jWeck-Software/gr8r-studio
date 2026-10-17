<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Keeps when the signed-in Member was last active (Member::seen()), which their record's `last`
 * counts from: on signing in and on every request with a session, written at most once a minute.
 *
 * Runs after the firewall (8) and the rate limits that count after it (RateLimits, 4), so only
 * requests the firewall lets through count, and before the controller: the moment is stored on its
 * own, never inside a sync transaction, and the same request's snapshot shows it.
 */
final readonly class LastActive
{
    public function __construct(
        private Security $security,
        private MemberRepository $members,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        // A request without a session has no one signed in; asking would start one.
        if ($event->isMainRequest() && $event->getRequest()->hasPreviousSession()) {
            $this->seen($this->security->getUser());
        }
    }

    /** The sign-in itself, which the firewall answers before the request gets here. */
    #[AsEventListener]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $this->seen($event->getUser());
    }

    private function seen(mixed $user): void
    {
        // A User without a Workspace yet (onboarding) is no Member.
        $member = $user instanceof User ? $this->members->forUser($user) : null;
        if (null !== $member && $member->seen($this->clock->now())) {
            $this->em->flush();
        }
    }
}
