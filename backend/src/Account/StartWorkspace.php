<?php

namespace App\Account;

use Symfony\Component\Validator\Constraints as Assert;

/** The body of POST /api/workspaces: the new Workspace's name, URL slug and colour. */
final readonly class StartWorkspace
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name your workspace.', normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'A Workspace name can have at most 120 characters.')]
        public string $name = '',
        // What the frontend's slug() makes of what is typed, as for the Workspace settings (WorkspaceSettingsHandler).
        #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'The Workspace URL must be lowercase letters and digits, with single hyphens between them, like gr8r-studio.')]
        #[Assert\Length(max: 120, maxMessage: 'A Workspace URL can have at most 120 characters.')]
        public string $url = '',
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/', message: 'The Workspace colour must be like #5A67D8.')]
        public string $c = '',
    ) {
    }
}
