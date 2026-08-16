<?php

namespace App\Homepage\Sections;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The load-bearing function of the public render path.
 *
 * TOTAL: it must never throw, for any input whatsoever. Whatever it cannot
 * make sense of is dropped, so the front door degrades instead of breaking.
 */
final class SectionNormalizer
{
    public const MAX_BLOCKS = 100;

    /** @return array<int,array<string,mixed>> */
    public static function normalize(mixed $raw): array
    {
        try {
            return self::run($raw);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function run(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        $out = [];

        foreach (array_slice($raw, 0, self::MAX_BLOCKS) as $index => $row) {
            try {
                $block = self::row($row, (int) $index);
            } catch (Throwable) {
                $block = null;
            }

            if ($block !== null) {
                $out[] = $block;
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    private static function row(mixed $row, int $index): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $type = $row['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return null;
        }

        $section = SectionRegistry::get($type);

        if ($section === null) {
            return null; // unknown type: dropped from the payload, kept in storage
        }

        if (array_key_exists('enabled', $row) && ! self::truthy($row['enabled'])) {
            return null;
        }

        $id = $row['id'] ?? null;
        $data = $section->normalize($row['data'] ?? null);

        foreach ($section->imageFields() as $name) {
            $data[self::urlKey($name)] = self::imageUrl($data[$name] ?? null);
        }

        return [
            'id' => is_string($id) && $id !== '' ? $id : 'auto-'.$index,
            'type' => $type,
            'variant' => $section->normalizeVariant($row['variant'] ?? null),
            'data' => $data,
        ];
    }

    /** `foo_path` is accompanied by `foo_url`; any other name simply gains `_url`. */
    private static function urlKey(string $name): string
    {
        return preg_replace('/_path$/', '', $name).'_url';
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

    /** Never a URL for a file that is not there. */
    private static function imageUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
