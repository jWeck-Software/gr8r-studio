<?php

namespace App\Controller;

use App\Entity\Member;
use App\Sync\Sync;
use App\Sync\SyncRejected;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SyncController
{
    #[Route('/api/sync', name: 'api_sync', methods: ['POST'])]
    public function __invoke(Request $request, Member $member, Sync $sync): JsonResponse
    {
        try {
            $sync->apply($member, json_decode($request->getContent(), true));
        } catch (SyncRejected $rejected) {
            return new JsonResponse($rejected->body(), $rejected->status());
        }

        return new JsonResponse(['ok' => true]);
    }
}
