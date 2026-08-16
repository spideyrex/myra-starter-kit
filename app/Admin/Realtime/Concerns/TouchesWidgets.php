<?php

namespace App\Admin\Realtime\Concerns;

use App\Admin\Realtime\WidgetSignal;
use Throwable;

/**
 * Opt-in. Declare on the model:
 *
 *     use TouchesWidgets;
 *     protected static array $widgetTopics = ['users'];
 *
 * NOT applied to any model in this bundle — attaching it to User would change
 * write-path behaviour on a live site. Registration is documented, not done.
 */
trait TouchesWidgets
{
    public static function bootTouchesWidgets(): void
    {
        $signal = static function (): void {
            try {
                $topics = static::widgetTopics();

                if ($topics !== []) {
                    WidgetSignal::emit(...$topics);
                }
            } catch (Throwable $e) {
                report($e);
            }
        };

        static::saved($signal);
        static::deleted($signal);
    }

    /** @return string[] */
    public static function widgetTopics(): array
    {
        $topics = property_exists(static::class, 'widgetTopics') ? static::$widgetTopics : [];

        return array_values(array_filter(
            is_array($topics) ? $topics : [],
            static fn ($t) => is_string($t) && $t !== '',
        ));
    }
}
