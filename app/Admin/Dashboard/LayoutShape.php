<?php

namespace App\Admin\Dashboard;

use Illuminate\Validation\ValidationException;

/**
 * Shape and size validation for a stored dashboard layout payload.
 *
 * EXPLICIT NON-GOAL: no widget-key lookup and no permission checking. That
 * authority belongs to WidgetInstance and to the client-side permission filter.
 * Do not duplicate it here.
 */
final class LayoutShape
{
    public const MAX_ENTRIES = 48;

    public const MAX_INSTANCES = 12;

    public const MAX_BYTES = 8192;

    private const KEY = '/^[a-z0-9][a-z0-9._:#-]{0,63}$/i';

    private const ENTRY_KEYS = ['key', 'order', 'colSpan', 'rowSpan', 'hidden'];

    private const INSTANCE_KEYS = ['key', 'catalogue', 'binding', 'title', 'chartType', 'colSpan', 'rowSpan', 'order'];

    private const SPAN_BREAKPOINTS = ['sm', 'lg', 'xl'];

    /**
     * @return array{version:int,entries:array<int,array>,instances:array<int,array>}
     *
     * @throws ValidationException 422 — never a truncated payload.
     */
    public static function assert(mixed $payload, string $attribute): array
    {
        $json = is_array($payload) ? json_encode($payload) : false;

        if ($json === false) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }
        if (strlen($json) > self::MAX_BYTES) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        if (array_diff(array_keys($payload), ['version', 'entries', 'instances']) !== []
            || array_diff(['version', 'entries', 'instances'], array_keys($payload)) !== []) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        if (($payload['version'] ?? null) !== 1) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        $entries = $payload['entries'];
        $instances = $payload['instances'];

        if (! is_array($entries) || ! array_is_list($entries)
            || ! is_array($instances) || ! array_is_list($instances)) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        if (count($entries) > self::MAX_ENTRIES) {
            self::fail($attribute, 'dashboardLayout.errors.tooManyEntries');
        }
        if (count($instances) > self::MAX_INSTANCES) {
            self::fail($attribute, 'dashboardLayout.errors.tooManyInstances');
        }

        return [
            'version' => 1,
            'entries' => array_map(static fn ($e) => self::assertEntry($e, $attribute), $entries),
            'instances' => array_map(static fn ($i) => self::assertInstance($i, $attribute), $instances),
        ];
    }

    // >>> MYRA v2.7 [A] START
    /**
     * Non-throwing READ-time sibling of assert(). Drops malformed elements
     * instead of raising: throwing on read would blank a dashboard, and the
     * payload may have been authored by somebody other than the viewer.
     *
     * @return array{version:int,entries:array<int,array>,instances:array<int,array>}
     */
    public static function filter(mixed $payload): array
    {
        $out = ['version' => 1, 'entries' => [], 'instances' => []];

        if (! is_array($payload)) {
            return $out;
        }

        $entries = $payload['entries'] ?? [];
        $instances = $payload['instances'] ?? [];

        if (is_array($entries) && array_is_list($entries)) {
            foreach ($entries as $entry) {
                if (count($out['entries']) >= self::MAX_ENTRIES) {
                    break;
                }
                try {
                    $out['entries'][] = self::assertEntry($entry, 'payload');
                } catch (\Throwable) {
                    // drop
                }
            }
        }

        if (is_array($instances) && array_is_list($instances)) {
            foreach ($instances as $instance) {
                if (count($out['instances']) >= self::MAX_INSTANCES) {
                    break;
                }
                try {
                    $out['instances'][] = self::assertInstance($instance, 'payload');
                } catch (\Throwable) {
                    // drop
                }
            }
        }

        return $out;
    }
    // <<< MYRA v2.7 [A] END

    private static function assertEntry(mixed $entry, string $attribute): array
    {
        if (! is_array($entry) || array_is_list($entry)) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }
        if (array_diff(array_keys($entry), self::ENTRY_KEYS) !== []) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        $out = [
            'key' => self::assertKey($entry['key'] ?? null, $attribute),
            'order' => self::assertInt($entry['order'] ?? null, 0, 255, $attribute),
        ];

        if (array_key_exists('colSpan', $entry)) {
            $out['colSpan'] = self::assertColSpan($entry['colSpan'], $attribute);
        }
        if (array_key_exists('rowSpan', $entry)) {
            $out['rowSpan'] = self::assertInt($entry['rowSpan'], 1, 4, $attribute);
        }
        if (array_key_exists('hidden', $entry)) {
            if (! is_bool($entry['hidden'])) {
                self::fail($attribute, 'dashboardLayout.errors.shape');
            }
            $out['hidden'] = $entry['hidden'];
        }

        return $out;
    }

    private static function assertInstance(mixed $instance, string $attribute): array
    {
        if (! is_array($instance) || array_is_list($instance)) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }
        if (array_diff(array_keys($instance), self::INSTANCE_KEYS) !== []) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        $out = [
            'key' => self::assertKey($instance['key'] ?? null, $attribute),
            'catalogue' => self::assertKey($instance['catalogue'] ?? null, $attribute),
            'binding' => self::assertBinding($instance['binding'] ?? [], $attribute),
        ];

        if (array_key_exists('title', $instance) && $instance['title'] !== null) {
            if (! is_string($instance['title']) || mb_strlen($instance['title']) > 60) {
                self::fail($attribute, 'dashboardLayout.errors.shape');
            }
            $out['title'] = $instance['title'];
        }
        if (array_key_exists('chartType', $instance) && $instance['chartType'] !== null) {
            $out['chartType'] = self::assertKey($instance['chartType'], $attribute);
        }
        if (array_key_exists('colSpan', $instance)) {
            $out['colSpan'] = self::assertColSpan($instance['colSpan'], $attribute);
        }
        if (array_key_exists('rowSpan', $instance)) {
            $out['rowSpan'] = self::assertInt($instance['rowSpan'], 1, 4, $attribute);
        }
        if (array_key_exists('order', $instance)) {
            $out['order'] = self::assertInt($instance['order'], 0, 255, $attribute);
        }

        return $out;
    }

    /** An assoc array of at most 12 keys of scalars or string lists. */
    private static function assertBinding(mixed $binding, string $attribute): array
    {
        if (! is_array($binding)) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }
        if ($binding === []) {
            return [];
        }
        if (array_is_list($binding) || count($binding) > 12) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        foreach ($binding as $key => $value) {
            if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
                self::fail($attribute, 'dashboardLayout.errors.shape');
            }

            if (is_array($value)) {
                if (! array_is_list($value) || count($value) > 12) {
                    self::fail($attribute, 'dashboardLayout.errors.shape');
                }
                foreach ($value as $item) {
                    if (! is_string($item) || mb_strlen($item) > 64) {
                        self::fail($attribute, 'dashboardLayout.errors.shape');
                    }
                }

                continue;
            }

            if ($value !== null && ! is_scalar($value)) {
                self::fail($attribute, 'dashboardLayout.errors.shape');
            }
        }

        return $binding;
    }

    private static function assertColSpan(mixed $span, string $attribute): int|array
    {
        if (is_int($span)) {
            return self::assertInt($span, 1, 4, $attribute);
        }

        if (! is_array($span) || array_is_list($span) || $span === []) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }
        if (array_diff(array_keys($span), self::SPAN_BREAKPOINTS) !== []) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        $out = [];
        foreach ($span as $breakpoint => $value) {
            $out[$breakpoint] = self::assertInt($value, 1, 4, $attribute);
        }

        return $out;
    }

    private static function assertInt(mixed $value, int $min, int $max, string $attribute): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        return $value;
    }

    private static function assertKey(mixed $value, string $attribute): string
    {
        if (! is_string($value) || preg_match(self::KEY, $value) !== 1) {
            self::fail($attribute, 'dashboardLayout.errors.shape');
        }

        return $value;
    }

    private static function fail(string $attribute, string $messageKey): never
    {
        throw ValidationException::withMessages([$attribute => __($messageKey)]);
    }
}
