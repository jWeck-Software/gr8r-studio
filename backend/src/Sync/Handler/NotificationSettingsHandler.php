<?php

namespace App\Sync\Handler;

use App\Sync\PersonalSetting;
use App\Sync\SyncRejected;

/** The Member's notification settings (`notifPrefs`): which emails and push messages they want, each on or off. */
final readonly class NotificationSettingsHandler extends PersonalSetting
{
    private const int MAX_SETTINGS = 100;

    public function type(): string
    {
        return 'notifPrefs';
    }

    protected function valid(mixed $data): mixed
    {
        $settings = self::object($data, 'The notification settings must be an object of settings, each true or false.');
        if (\count($settings) > self::MAX_SETTINGS || [] !== array_filter($settings, static fn (mixed $on): bool => !\is_bool($on))) {
            throw SyncRejected::invalid(\sprintf('The notification settings must be at most %d settings, each true or false.', self::MAX_SETTINGS));
        }

        return $settings;
    }
}
