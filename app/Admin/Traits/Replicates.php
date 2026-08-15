<?php

namespace App\Admin\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Duplicate a record from a row action.
 *
 * Everything the client sends is a hint: column names are filtered against the
 * model's fillable set, relation names against the model's `$replicable`
 * whitelist, and the record itself is fetched through the model's global scopes
 * so ownership still applies.
 */
trait Replicates
{
    /** @return class-string<Model> */
    abstract protected function replicateModel(): string;

    /** Shield module slug, e.g. 'articles'. */
    abstract protected function replicateModule(): string;

    /** Never copied. */
    protected function replicateGuarded(): array
    {
        return ['id', 'created_at', 'updated_at', 'deleted_at'];
    }

    /** Columns carrying a unique index — regenerated on the clone. */
    protected function replicateUniqueColumns(): array
    {
        return [];
    }

    public function replicate(Request $request, int $id): RedirectResponse
    {
        $this->authorizeReplicate();

        $data = $request->validate([
            'except' => ['sometimes', 'array'],
            'except.*' => ['string', 'max:64'],
            'only' => ['sometimes', 'nullable', 'array'],
            'only.*' => ['string', 'max:64'],
            'relations' => ['sometimes', 'array'],
            'relations.*' => ['string', 'max:64'],
            'overrides' => ['sometimes', 'array'],
            'suffix' => ['sometimes', 'nullable', 'array'],
            'suffix.field' => ['sometimes', 'nullable', 'string', 'max:64'],
            'suffix.template' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->replicateModel();

        // Global scopes (ownership, soft deletes) apply: a record the caller
        // may not see 404s instead of being cloned.
        $record = $modelClass::findOrFail($id);

        DB::transaction(fn () => $this->replicateRecord(
            $record,
            (array) ($data['except'] ?? []),
            $data['only'] ?? null,
            (array) ($data['relations'] ?? []),
            (array) ($data['overrides'] ?? []),
            $data['suffix'] ?? null,
        ));

        return back()->with('success', 'Record duplicated successfully.');
    }

    protected function authorizeReplicate(): void
    {
        abort_unless(Auth::user()?->can($this->replicateModule() . '.create') === true, 403);
    }

    protected function replicateRecord(
        Model $record,
        array $except,
        ?array $only,
        array $relations,
        array $overrides,
        ?array $suffix,
    ): Model {
        $guarded = $this->replicateGuarded();

        // Eloquent's own replicate() copies raw attributes, so casts survive.
        $replica = $record->replicate(array_values(array_unique(array_merge($guarded, $except))));

        if (! empty($only)) {
            $replica->setRawAttributes(array_intersect_key(
                $replica->getAttributes(),
                array_flip(array_diff($only, $guarded)),
            ));
        }

        // Overrides are whitelisted against the model's fillable set, so an
        // attacker cannot repoint foreign keys or ownership columns.
        $replica->forceFill(array_intersect_key($overrides, array_flip($record->getFillable())));

        $suffixField = $suffix['field'] ?? null;

        if ($suffixField && in_array($suffixField, $record->getFillable(), true)) {
            $replica->{$suffixField} = $this->uniqueSuffixed(
                $record,
                $suffixField,
                (string) ($record->{$suffixField} ?? ''),
                ($suffix['template'] ?? null) ?: ':value (copy)',
            );
        }

        // Unique-indexed columns are always regenerated, even when the caller
        // excluded them — a dropped NOT NULL column would just fail the insert.
        foreach ($this->replicateUniqueColumns() as $column) {
            if ($column === $suffixField) {
                continue;
            }
            $seed = (string) ($replica->{$column} ?? $record->{$column} ?? '');
            $replica->{$column} = $this->uniqueSuffixed($record, $column, $seed, ':value-copy');
        }

        $replica->save();

        $this->replicateRelations($record, $replica, $relations);

        return $replica;
    }

    /** One level deep only — unbounded recursion from one click is a DoS. */
    protected function replicateRelations(Model $record, Model $replica, array $relations): void
    {
        $allowed = array_values(array_intersect($relations, $this->replicableRelations($record)));

        foreach ($allowed as $name) {
            if (! method_exists($record, $name)) {
                continue;
            }

            $relation = $record->{$name}();

            if ($relation instanceof BelongsToMany) {
                $pivots = [];
                foreach ($record->{$name} as $related) {
                    $attributes = $related->pivot?->getAttributes() ?? [];
                    unset(
                        $attributes['id'],
                        $attributes[$relation->getForeignPivotKeyName()],
                        $attributes[$relation->getRelatedPivotKeyName()],
                    );
                    $pivots[$related->getKey()] = $attributes;
                }
                $replica->{$name}()->sync($pivots);
            } elseif ($relation instanceof HasMany) {
                foreach ($record->{$name} as $child) {
                    $clone = $child->replicate();
                    $clone->{$relation->getForeignKeyName()} = $replica->getKey();
                    $clone->save();
                }
            }
        }
    }

    /** @return string[] relation names the model opts in to cloning */
    protected function replicableRelations(Model $record): array
    {
        return property_exists($record, 'replicable') ? (array) $record::$replicable : [];
    }

    /** "X" → "X (copy)" → "X (copy 2)" …, checked against the column's index. */
    protected function uniqueSuffixed(Model $record, string $column, string $value, string $template): string
    {
        $base = str_replace(':value', $value, $template);

        if (! $this->valueExists($record, $column, $base)) {
            return $base;
        }

        for ($n = 2; $n <= 100; $n++) {
            $candidate = str_ends_with($base, ')')
                ? substr($base, 0, -1) . ' ' . $n . ')'
                : $base . '-' . $n;

            if (! $this->valueExists($record, $column, $candidate)) {
                return $candidate;
            }
        }

        return $base . '-' . uniqid();
    }

    protected function valueExists(Model $record, string $column, string $value): bool
    {
        // Uniqueness is DB-wide: ignore ownership and soft-delete scopes.
        return $record->newQuery()->withoutGlobalScopes()->where($column, $value)->exists();
    }
}
