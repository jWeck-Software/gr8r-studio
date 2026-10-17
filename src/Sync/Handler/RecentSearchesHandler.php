<?php

namespace App\Sync\Handler;

use App\Sync\PersonalSetting;
use App\Sync\SyncRejected;

/** What the Member searched for lately (`recentSearches`), newest first; the frontend keeps five. */
final readonly class RecentSearchesHandler extends PersonalSetting
{
    private const int MAX_SEARCHES = 20;
    private const int MAX_LENGTH = 200;

    public function type(): string
    {
        return 'recentSearches';
    }

    protected function valid(mixed $data): mixed
    {
        $valid = \is_array($data) && array_is_list($data) && \count($data) <= self::MAX_SEARCHES
            && [] === array_filter($data, static fn (mixed $q): bool => !\is_string($q) || mb_strlen($q) > self::MAX_LENGTH);

        return $valid ? $data : throw SyncRejected::invalid(\sprintf('The recent searches must be a list of at most %d texts of at most %d characters.', self::MAX_SEARCHES, self::MAX_LENGTH));
    }
}
