<?php

namespace App\Controller;

use App\Entity\Member;
use App\Sync\Batch;
use App\Sync\Sync;
use App\Sync\SyncRejected;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class SyncController extends AbstractController
{
    /** A body that is no `{"ops": [...]}` is 422 before this runs (JsonApiErrorListener). */
    #[Route('/api/sync', name: 'api_sync', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload(acceptFormat: 'json', mapWhenEmpty: true)] Batch $batch, Member $member, Sync $sync): JsonResponse
    {
        try {
            $reply = $sync->apply($member, $batch->ops);
        } catch (SyncRejected $rejected) {
            return new JsonResponse($rejected->body(), $rejected->status());
        }

        return new JsonResponse(['ok' => true, ...$reply]);
    }
}
