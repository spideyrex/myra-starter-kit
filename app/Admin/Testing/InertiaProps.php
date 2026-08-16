<?php

namespace App\Admin\Testing;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Throwable;

/** Reads the props of a real Inertia response — no DOM, no browser. */
final class InertiaProps
{
    public static function of(TestResponse $r): array
    {
        $page = $r->viewData('page');

        Assert::assertIsArray(
            $page,
            'The response carries no Inertia page object. Did the route return Inertia::render()?',
        );

        Assert::assertArrayHasKey('props', $page, 'The Inertia page object has no props.');

        return (array) $page['props'];
    }

    public static function get(TestResponse $r, string $dotKey): mixed
    {
        $value = self::of($r);

        foreach (explode('.', $dotKey) as $segment) {
            if (is_object($value)) {
                $value = self::normalise($value);
            }

            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                Assert::fail("Inertia prop [{$dotKey}] is missing (stopped at [{$segment}]).");
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** The wire shape: models and resources become plain arrays, exactly as the client sees them. */
    public static function normalise(mixed $value): mixed
    {
        if (! is_array($value) && ! is_object($value)) {
            return $value;
        }

        try {
            $encoded = json_encode($value);
        } catch (Throwable) {
            return $value;
        }

        return $encoded === false ? $value : json_decode($encoded, true);
    }
}
