<?php

namespace App\Account;

/** The body of DELETE /api/workspace: the Workspace's name, typed to confirm, as the app's dialog asks. */
final readonly class DeleteWorkspace
{
    public function __construct(
        public string $name = '',
    ) {
    }
}
