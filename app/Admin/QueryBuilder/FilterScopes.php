<?php

namespace App\Admin\QueryBuilder;

/**
 * The closed set of surfaces a natural-language filter may target. The client
 * names a SCOPE, never a field list — the FieldSet below is a controller-side
 * literal, exactly like every other one in the codebase.
 *
 * The `users` literal is duplicated from UserController::filterFieldSet() on
 * purpose: an AI surface must not be able to widen another controller's
 * whitelist by editing it.
 */
final class FilterScopes
{
    /** @return array<string,string> scope key => label key */
    public static function map(): array
    {
        return [
            'users' => 'assistant.scope.users',
            'activity' => 'assistant.scope.activity',
        ];
    }

    public static function resolve(string $scope): FieldSet
    {
        return match ($scope) {
            'users' => self::users(),
            'activity' => self::activity(),
            default => abort(404),
        };
    }

    private static function users(): FieldSet
    {
        return FieldSet::make([
            FieldSpec::text('name')->labelKey('filters.field.name')->contains(),
            FieldSpec::text('email')->labelKey('filters.field.email')->contains(),
            FieldSpec::text('phone')->labelKey('filters.field.phone')->nullable(),
            FieldSpec::select('status')->labelKey('filters.field.status')->options(['active', 'suspended', 'pending']),
            FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
            FieldSpec::date('email_verified_at')->labelKey('filters.field.verifiedAt')->nullable(),
            FieldSpec::relation('roles')->labelKey('filters.field.roles')->relationship('roles', 'name'),
        ])->maxRules((int) config('myra.filters.max_rules', 25))
            ->maxDepth((int) config('myra.filters.max_depth', 3));
    }

    private static function activity(): FieldSet
    {
        return FieldSet::make([
            FieldSpec::text('description')->labelKey('reports.field.event')->contains(),
            FieldSpec::text('subject_type')->labelKey('reports.field.subjectType')->contains(),
            FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
        ])->maxRules((int) config('myra.filters.max_rules', 25))
            ->maxDepth((int) config('myra.filters.max_depth', 3));
    }
}
