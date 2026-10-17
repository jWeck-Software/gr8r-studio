<?php

namespace App\Controller;

use App\Entity\Member;
use App\File\FileCopy;
use App\File\FileRefused;
use App\File\Files;
use App\File\NewFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class FileController extends AbstractController
{
    /** Multipart: `file`, `id`, `project` and optionally `task`. Answers 201 with the File record. */
    #[Route('/api/files', name: 'api_file_upload', methods: ['POST'])]
    public function upload(#[MapRequestPayload(acceptFormat: 'form')] NewFile $body, Member $member, Files $files): JsonResponse
    {
        try {
            return new JsonResponse($files->store($member, $body->file, $body->id, $body->project, $body->task), 201);
        } catch (FileRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }
    }

    /**
     * The File's content, for whoever sees its Project. The route says `{file}`, not `{id}`:
     * Doctrine would resolve `Member $member` from an `{id}`.
     */
    #[Route('/api/files/{file}/content', name: 'api_file_content', methods: ['GET'])]
    public function content(string $file, Member $member, Files $files): Response
    {
        try {
            return $files->download($member, $file);
        } catch (FileRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }
    }

    /** Duplicates the File, content included, under the id the client sends: `{"id": "…"}`. Answers 201 with the copy. */
    #[Route('/api/files/{file}/copy', name: 'api_file_copy', methods: ['POST'])]
    public function copy(string $file, #[MapRequestPayload(acceptFormat: 'json')] FileCopy $body, Member $member, Files $files): JsonResponse
    {
        try {
            return new JsonResponse($files->copy($member, $file, $body->id), 201);
        } catch (FileRefused $refused) {
            return new JsonResponse($refused->body(), $refused->status);
        }
    }
}
