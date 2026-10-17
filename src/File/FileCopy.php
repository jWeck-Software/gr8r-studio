<?php

namespace App\File;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/files/{file}/copy: the id the client generated for the copy. */
final readonly class FileCopy
{
    public function __construct(
        #[Assert\NotBlank(message: 'The copy needs an id.')]
        public string $id = '',
    ) {
    }
}
