<?php

namespace App\Admin\Report;

use App\Admin\Views\ViewShape;
use Illuminate\Validation\ValidationException;

/**
 * Shape and size validation for a saved report view's payload, mirroring
 * ViewShape exactly.
 *
 * EXPLICIT NON-GOAL: no field, measure or operator whitelisting. The replayed
 * state goes back through ReportRequest::parse(), which is the same authority
 * that validates a live one.
 */
final class ReportShape
{
    public const MAX_BYTES = 16384;

    private const KEYS = ['period', 'compare', 'dimension', 'bucket', 'measures', 'limit', 'chart', 'mode', 'query', 'cross', 'report'];

    /** @throws ValidationException 422, never a silently truncated state. */
    public static function assertState(mixed $state, string $attribute): void
    {
        if ($state === null) {
            return;
        }

        if (! is_array($state) || array_is_list($state)) {
            self::fail($attribute, 'The saved report state is malformed.');
        }

        $json = json_encode($state);
        if ($json === false || strlen($json) > self::MAX_BYTES) {
            self::fail($attribute, 'The saved report state is too large.');
        }

        if (array_diff(array_keys($state), self::KEYS) !== []) {
            self::fail($attribute, 'The saved report state is malformed.');
        }

        if (isset($state['measures']) && (! is_array($state['measures']) || ! array_is_list($state['measures']))) {
            self::fail($attribute, 'The saved report state is malformed.');
        }

        if (isset($state['cross'])) {
            // An empty map round-trips through JSON as [], which array_is_list()
            // reports as a list — so only a NON-empty list is malformed.
            if (! is_array($state['cross']) || ($state['cross'] !== [] && array_is_list($state['cross']))) {
                self::fail($attribute, 'The saved report state is malformed.');
            }

            if (count($state['cross']) > ReportRequest::MAX_CROSS_FILTERS) {
                self::fail($attribute, 'The saved report state has too many cross-filters.');
            }

            foreach ($state['cross'] as $name => $tree) {
                ViewShape::assertQueryTree($tree, $attribute . '.cross.' . $name);
            }
        }

        ViewShape::assertQueryTree($state['query'] ?? null, $attribute . '.query');
    }

    private static function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
