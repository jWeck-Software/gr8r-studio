<?php

namespace App\Entity;

/**
 * A Member's Role in their Workspace, stored and sent as its name. The cases keep the frontend's
 * order (ROLES in assets/src/core/constants.js), which the sync's 422 lists them in.
 */
enum Role: string
{
    case Owner = 'Owner';
    case Admin = 'Admin';
    case Member = 'Member';
    case Guest = 'Guest';

    /** @return list<string> the Roles as the frontend writes them */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
