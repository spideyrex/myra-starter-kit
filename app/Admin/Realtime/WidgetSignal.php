<?php

namespace App\Admin\Realtime;

use App\Admin\Realtime\Jobs\EmitWidgetSignal;
use App\Admin\Report\ReportRegistry;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fan-out of change SIGNALS. Inert unless `myra.realtime.enabled` is true, and
 * every path is wrapped so a signal can never fail the write that caused it.
 */
final class WidgetSignal
{
    public const CHUNK = 200;

    public const LOCK_SECONDS = 5;

    public static function enabled(): bool
    {
        return config('myra.realtime.enabled') === true;
    }

    /**
     * The `myra.dashboard.{id}` predicate. One implementation, used by
     * routes/channels.php and asserted directly by the channel test.
     */
    public static function authorizesChannel(mixed $user, mixed $id): bool
    {
        return $user !== null
            && is_numeric($id)
            && (int) ($user->id ?? 0) === (int) $id;
    }

    /** Fan-out to the acting user only. No-op unless realtime is enabled. */
    public static function emitTo(?Authenticatable $user, string ...$topics): void
    {
        if (! self::enabled() || $user === null) {
            return;
        }

        $userId = (int) $user->getAuthIdentifier();
        $topics = self::clean($topics);

        if ($userId <= 0 || $topics === []) {
            return;
        }

        self::dispatch($userId, $topics);
    }

    /** Fan-out to every user permitted to see the topic's report, chunked. */
    public static function emit(string ...$topics): void
    {
        if (! self::enabled()) {
            return;
        }

        $topics = self::clean($topics);

        if ($topics === []) {
            return;
        }

        try {
            User::query()
                ->select(['id'])
                ->chunkById(self::CHUNK, function ($users) use ($topics) {
                    foreach ($users as $user) {
                        $allowed = array_values(array_filter(
                            $topics,
                            static fn (string $topic) => self::permits($user, $topic),
                        ));

                        if ($allowed !== []) {
                            self::dispatch((int) $user->id, $allowed);
                        }
                    }
                });
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** A bulk update is ONE signal per (topic, user), not N. */
    private static function dispatch(int $userId, array $topics): void
    {
        try {
            $fresh = array_values(array_filter(
                $topics,
                static fn (string $topic) => Cache::add(
                    "myra.realtime.signal.{$userId}.{$topic}",
                    1,
                    self::LOCK_SECONDS,
                ),
            ));

            if ($fresh === []) {
                return;
            }

            EmitWidgetSignal::dispatch($userId, $fresh);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function permits(mixed $user, string $topic): bool
    {
        try {
            if (! ReportRegistry::has($topic)) {
                return false;
            }

            return $user instanceof Authorizable
                && $user->can(ReportRegistry::resolve($topic)->permissionAbility());
        } catch (Throwable) {
            return false;
        }
    }

    /** @param  string[]  $topics */
    private static function clean(array $topics): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($t) => is_string($t) ? trim($t) : '', $topics),
            static fn (string $t) => $t !== '' && preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/i', $t) === 1,
        )));
    }
}
