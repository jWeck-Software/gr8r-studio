<?php

namespace App\Account;

/** What every Workspace starts with, as the frontend has it: its Teams, and each Member's notification settings. */
final class WorkspaceDefaults
{
    /**
     * The Teams of src/core/constants.js (TEAMS_SEED) by the key the demo Workspace uses as their
     * ids; a new Workspace gives them ids of its own.
     *
     * @var array<string, array{name: string, icon: string, c: string, desc: string}>
     */
    public const array TEAMS = [
        'design' => ['name' => 'Design', 'icon' => 'palette', 'c' => '#8662C9', 'desc' => 'Product design, brand, and research'],
        'eng' => ['name' => 'Engineering', 'icon' => 'code', 'c' => '#3B82C4', 'desc' => 'Web, mobile, and platform engineering'],
        'mkt' => ['name' => 'Marketing', 'icon' => 'megaphone', 'c' => '#C54B78', 'desc' => 'Campaigns, content, and growth'],
        'product' => ['name' => 'Product', 'icon' => 'target', 'c' => '#C48A1E', 'desc' => 'Roadmap, planning, and QA'],
    ];

    /** A Member's notification settings (`notifPrefs`) until they change them, as src/data/seed.js has them. */
    public const array NOTIF_PREFS = [
        'email_mention' => true, 'email_assign' => true, 'email_digest' => true, 'email_comment' => false,
        'push_mention' => true, 'push_assign' => true, 'push_comment' => true, 'push_due' => true,
        'mention_all' => true, 'assign_self' => true,
    ];
}
