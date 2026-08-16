<?php

namespace App\Homepage\Sections;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The write path. Turns an editor payload into the stored shape.
 *
 * An unknown type is QUARANTINED, never rejected and never dropped: a rejected
 * save strands the author, and a dropped row destroys content the moment a
 * package is disabled or a deploy is rolled back.
 */
final class SectionWriter
{
    /** @return array<int,array<string,mixed>> */
    public static function prepare(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw ValidationException::withMessages(['blocks' => 'The page sections must be an ordered list.']);
        }

        if (count($raw) > SectionNormalizer::MAX_BLOCKS) {
            throw ValidationException::withMessages([
                'blocks' => 'A page may hold at most '.SectionNormalizer::MAX_BLOCKS.' sections.',
            ]);
        }

        $out = [];
        $errors = [];

        foreach ($raw as $index => $row) {
            $i = (int) $index;

            if (! is_array($row)) {
                $errors["blocks.{$i}"] = 'This section is malformed.';

                continue;
            }

            $type = $row['type'] ?? null;

            if (! is_string($type) || $type === '') {
                $errors["blocks.{$i}.type"] = 'This section has no type.';

                continue;
            }

            $id = is_string($row['id'] ?? null) && trim($row['id']) !== ''
                ? trim($row['id'])
                : (string) Str::ulid();

            $enabled = ! array_key_exists('enabled', $row) || self::truthy($row['enabled']);
            $section = SectionRegistry::get($type);

            if ($section === null) {
                $out[] = [
                    'id' => $id,
                    'type' => $type,
                    'enabled' => $enabled,
                    'data' => is_array($row['data'] ?? null) ? $row['data'] : [],
                ];

                continue;
            }

            $data = $section->sanitize(is_array($row['data'] ?? null) ? $row['data'] : []);

            foreach (self::missingRequired($section, $data, $i) as $key => $message) {
                $errors[$key] = $message;
            }

            $out[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => $enabled,
                'variant' => $section->normalizeVariant($row['variant'] ?? null),
                'data' => $data,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * Addressed so the editor can expand and scroll to the offending card.
     *
     * @return array<string,string>
     */
    private static function missingRequired(SectionType $section, array $data, int $i): array
    {
        $errors = [];

        foreach ($section->fieldList() as $field) {
            $value = $data[$field->name()] ?? null;

            if ($field->isRequired() && ($value === '' || $value === [] || $value === null)) {
                $errors["blocks.{$i}.data.{$field->name()}"] = 'This field is required.';
            }

            if ($field->typeValue() !== 'list' || ! is_array($value)) {
                continue;
            }

            foreach ($value as $n => $subRow) {
                foreach ($field->subFields() as $sub) {
                    if (! $sub->isRequired()) {
                        continue;
                    }

                    $subValue = $subRow[$sub->name()] ?? null;

                    if ($subValue === '' || $subValue === [] || $subValue === null) {
                        $errors["blocks.{$i}.data.{$field->name()}.{$n}.{$sub->name()}"] = 'This field is required.';
                    }
                }
            }
        }

        return $errors;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
