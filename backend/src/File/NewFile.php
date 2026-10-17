<?php

namespace App\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/** The multipart body of POST /api/files: the file, the id the client generated, its Project and, optionally, its Task. */
final readonly class NewFile
{
    public function __construct(
        #[Assert\NotNull(message: 'Choose a file to upload.')]
        public ?UploadedFile $file = null,
        #[Assert\NotBlank(message: 'A File needs an id.')]
        public string $id = '',
        #[Assert\NotBlank(message: 'A File needs a Project.')]
        public string $project = '',
        public ?string $task = null,
    ) {
    }
}
