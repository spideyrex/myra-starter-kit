<?php

namespace App\Admin\Views;

use Illuminate\Validation\ValidationException;

/**
 * Shape and size validation for a saved view's `payload.query` blob.
 *
 * EXPLICIT NON-GOAL: ViewShape performs NO field or operator whitelisting.
 * A saved view is replayed as URL query params through the *same* controller
 * path as a live filter, so whatever validates the live tree also validates the
 * replayed one. Until a rule compiler lands, no rule tree reaches SQL anywhere
 * in the codebase. Do not attempt semantic validation here and do not duplicate
 * an operator list — that authority belongs to the query-builder bundle.
 */
final class ViewShape
{
    public const MAX_RULES = 25;
    public const MAX_DEPTH = 3;
    public const MAX_BYTES = 16384;

    /**
     * @throws ValidationException 422, never a silently truncated tree.
     */
    public static function assertQueryTree(mixed $tree, string $attribute): void
    {
        if ($tree === null) {
            return;
        }

        $json = json_encode($tree);
        if ($json === false) {
            self::fail($attribute, 'The saved query is malformed.');
        }
        if (strlen($json) > self::MAX_BYTES) {
            self::fail($attribute, 'The saved query is too large.');
        }

        $rules = 0;
        self::assertGroup($tree, $attribute, 1, $rules);

        if ($rules > self::MAX_RULES) {
            self::fail($attribute, 'The saved query has too many conditions.');
        }
    }

    private static function assertGroup(mixed $node, string $attribute, int $depth, int &$rules): void
    {
        if ($depth > self::MAX_DEPTH) {
            self::fail($attribute, 'The saved query is nested too deeply.');
        }

        if (! is_array($node) || array_is_list($node)) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        $allowed = ['conjunction', 'rules', 'groups'];
        if (array_diff(array_keys($node), $allowed) !== []) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        if (! in_array($node['conjunction'] ?? null, ['and', 'or'], true)) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        $ruleList = $node['rules'] ?? [];
        $groupList = $node['groups'] ?? [];
        if (! is_array($ruleList) || ! array_is_list($ruleList)
            || ! is_array($groupList) || ! array_is_list($groupList)) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        foreach ($ruleList as $rule) {
            $rules++;
            if ($rules > self::MAX_RULES) {
                self::fail($attribute, 'The saved query has too many conditions.');
            }
            self::assertRule($rule, $attribute);
        }

        foreach ($groupList as $group) {
            self::assertGroup($group, $attribute, $depth + 1, $rules);
        }
    }

    private static function assertRule(mixed $rule, string $attribute): void
    {
        if (! is_array($rule) || array_is_list($rule)) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        if (array_diff(array_keys($rule), ['field', 'operator', 'value']) !== []) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? null;
        if (! is_string($field) || $field === '' || mb_strlen($field) > 64) {
            self::fail($attribute, 'The saved query is malformed.');
        }
        if (! is_string($operator) || $operator === '' || mb_strlen($operator) > 32) {
            self::fail($attribute, 'The saved query is malformed.');
        }

        $value = $rule['value'] ?? null;
        if (is_array($value)) {
            if (! array_is_list($value) || count($value) > 32) {
                self::fail($attribute, 'The saved query is malformed.');
            }
            foreach ($value as $item) {
                if (! self::isScalarish($item)) {
                    self::fail($attribute, 'The saved query is malformed.');
                }
            }

            return;
        }

        if (! self::isScalarish($value)) {
            self::fail($attribute, 'The saved query is malformed.');
        }
    }

    private static function isScalarish(mixed $value): bool
    {
        return $value === null || is_scalar($value);
    }

    private static function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
