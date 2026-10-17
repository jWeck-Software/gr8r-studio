<?php

namespace App\Security;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The signed-in User is no Member of any Workspace, for instance because they were removed from
 * it: 403 `no_workspace` (JsonApiErrorListener).
 */
final class NoWorkspace extends AccessDeniedHttpException
{
    public const string ERROR = 'no_workspace';

    public function __construct()
    {
        parent::__construct('You are not a Member of any Workspace.');
    }
}
