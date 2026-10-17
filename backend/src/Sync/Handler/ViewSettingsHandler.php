<?php

namespace App\Sync\Handler;

use App\Sync\PersonalSetting;
use App\Sync\SyncRejected;

/**
 * The Member's view settings (`views`): filters, sort, grouping, hidden columns and column widths of
 * each task view, by the frontend's view key ("p:<Project id>", "sv:<Saved view id>", "mytasks", …).
 * Their content is the frontend's (viewOf() in src/shell/view-engine.js); the server checks that each
 * is an object and that its column widths (`colW`) are numbers, which the snapshot returns as an object
 * even when empty.
 */
final readonly class ViewSettingsHandler extends PersonalSetting
{
    public function type(): string
    {
        return 'views';
    }

    protected function valid(mixed $data): mixed
    {
        $views = self::object($data, 'The view settings must be an object of views by their key.');
        foreach ($views as $key => $view) {
            $view = self::object($view, \sprintf('The view "%s" must be an object.', $key));
            if (\array_key_exists('colW', $view)) {
                $widths = self::object($view['colW'], \sprintf('The column widths of the view "%s" must be an object.', $key));
                if ([] !== array_filter($widths, static fn (mixed $w): bool => !\is_int($w) && !\is_float($w))) {
                    throw SyncRejected::invalid(\sprintf('The column widths of the view "%s" must be numbers.', $key));
                }
            }
        }

        return $views;
    }
}
