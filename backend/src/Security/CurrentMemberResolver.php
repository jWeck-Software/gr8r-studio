<?php

namespace App\Security;

use App\Entity\Member;
use App\Entity\User;
use App\Repository\MemberRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Gives controllers the Member the signed-in User acts as, via a `Member $member` argument. */
final readonly class CurrentMemberResolver implements ValueResolverInterface
{
    public function __construct(
        private Security $security,
        private MemberRepository $members,
    ) {
    }

    /** @return iterable<Member> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Member::class !== $argument->getType()) {
            return [];
        }
        $user = $this->security->getUser();
        $member = $user instanceof User ? $this->members->forUser($user) : null;
        if (null === $member) {
            throw new AccessDeniedHttpException('You are not a Member of any Workspace.');
        }

        return [$member];
    }
}
