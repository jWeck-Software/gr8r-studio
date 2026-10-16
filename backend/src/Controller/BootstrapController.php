<?php

namespace App\Controller;

use App\Entity\Member;
use App\Snapshot\Snapshot;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BootstrapController
{
    #[Route('/api/bootstrap', name: 'api_bootstrap', methods: ['GET'])]
    public function __invoke(Member $member, Snapshot $snapshot): JsonResponse
    {
        return new JsonResponse($snapshot->of($member));
    }
}
