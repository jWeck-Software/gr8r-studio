<?php

namespace App\Sync\Handler;

use App\Sync\PersonalSetting;
use App\Sync\SyncRejected;

/**
 * The Member's Preferences (`prefs`): theme, density, date format and the like. Each is a plain value;
 * the server checks those it relies on to render (theme, week start) and keeps the others as sent,
 * so a new setting in the frontend needs no change here.
 *
 * Name and title are the Member record's (`members`): Preferences that still carry them, as an
 * older browser's do, are stored without them.
 */
final readonly class PreferencesHandler extends PersonalSetting
{
    private const array PROFILE = ['name' => 0, 'title' => 0];
    private const int MAX_SETTINGS = 100;
    private const int MAX_LENGTH = 200;
    private const array THEMES = ['system', 'light', 'dark'];

    public function type(): string
    {
        return 'prefs';
    }

    protected function valid(mixed $data): mixed
    {
        $prefs = array_diff_key(self::object($data, 'The Preferences must be an object of settings.'), self::PROFILE);
        if (\count($prefs) > self::MAX_SETTINGS) {
            throw SyncRejected::invalid(\sprintf('The Preferences may hold at most %d settings.', self::MAX_SETTINGS));
        }
        foreach ($prefs as $key => $value) {
            if (null !== $value && !\is_scalar($value)) {
                throw self::wrong($key, 'text, a number, true, false or null');
            }
            if (\is_string($value) && mb_strlen($value) > self::MAX_LENGTH) {
                throw self::wrong($key, \sprintf('at most %d characters', self::MAX_LENGTH));
            }
        }
        if (isset($prefs['theme']) && !\in_array($prefs['theme'], self::THEMES, true)) {
            throw self::wrong('theme', 'one of '.implode(', ', self::THEMES));
        }
        if (isset($prefs['weekStart']) && !\in_array($prefs['weekStart'], range(0, 6), true)) {
            throw self::wrong('weekStart', 'a day from 0 (Sunday) to 6');
        }

        return $prefs;
    }

    private static function wrong(string|int $key, string $expected): SyncRejected
    {
        return SyncRejected::invalid(\sprintf('The Preference "%s" must be %s.', $key, $expected));
    }
}
