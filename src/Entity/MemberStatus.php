<?php

namespace App\Entity;

/** Whether a Member has joined their Workspace, or is invited and has no User yet. */
enum MemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
}
