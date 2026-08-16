<?php

namespace App\Homepage\Sections;

use App\Settings\HomepageSettings;
use Illuminate\Support\Str;

/**
 * The one conversion from the flat singleton settings into a block list.
 *
 * Used by BOTH the migration and SettingsSeeder so a fresh install and an
 * upgrade can never diverge. It reads plain rows, never the settings class,
 * because the migration runs before that class can hydrate.
 */
final class LegacyHomepageBlocks
{
    private const SECTIONS = ['hero', 'features', 'testimonials', 'pricing', 'cta'];

    /**
     * @param  array<string,mixed>  $rows  name => decoded payload
     * @return array<int,array<string,mixed>>
     */
    public static function fromRows(array $rows): array
    {
        if (! self::hasLegacyContent($rows)) {
            return [];
        }

        $blocks = [];

        foreach (self::order($rows['section_order'] ?? null) as $key) {
            $blocks[] = match ($key) {
                'hero' => self::block('hero', true, [
                    'title' => self::str($rows['hero_title'] ?? ''),
                    'subtitle' => self::str($rows['hero_subtitle'] ?? ''),
                    'cta_text' => self::str($rows['hero_cta_text'] ?? ''),
                    'cta_url' => self::str($rows['hero_cta_url'] ?? ''),
                    'image_path' => self::str($rows['hero_image_path'] ?? ''),
                ]),
                'features' => self::block('features', self::bool($rows['features_enabled'] ?? true), [
                    'title' => self::str($rows['features_title'] ?? ''),
                    'subtitle' => self::str($rows['features_subtitle'] ?? ''),
                    'items' => self::rows($rows['features'] ?? null, ['icon', 'title', 'description']),
                ]),
                'testimonials' => self::block('testimonials', self::bool($rows['testimonials_enabled'] ?? true), [
                    'title' => self::str($rows['testimonials_title'] ?? ''),
                    'subtitle' => self::str($rows['testimonials_subtitle'] ?? ''),
                    'items' => self::rows($rows['testimonials'] ?? null, ['name', 'role', 'quote']),
                ]),
                'pricing' => self::block('pricing', self::bool($rows['pricing_enabled'] ?? true), [
                    'title' => self::str($rows['pricing_title'] ?? ''),
                    'subtitle' => self::str($rows['pricing_subtitle'] ?? ''),
                    // `features` stays a comma-joined string: PricingTable.vue splits it.
                    'plans' => self::rows(
                        $rows['pricing_plans'] ?? null,
                        ['name', 'price', 'period', 'features', 'cta_text', 'cta_url'],
                        ['highlighted'],
                    ),
                ]),
                'cta' => self::block('cta', self::bool($rows['cta_enabled'] ?? true), [
                    'title' => self::str($rows['cta_title'] ?? ''),
                    'subtitle' => self::str($rows['cta_subtitle'] ?? ''),
                    'button_text' => self::str($rows['cta_button_text'] ?? ''),
                    'button_url' => self::str($rows['cta_button_url'] ?? ''),
                ]),
                default => null,
            };
        }

        return array_values(array_filter($blocks));
    }

    /** @return array<int,array<string,mixed>> */
    public static function fromSettings(HomepageSettings $settings): array
    {
        return self::fromRows($settings->toArray());
    }

    /** @return array<string,mixed> */
    private static function block(string $type, bool $enabled, array $data): array
    {
        return [
            'id' => (string) Str::ulid(),
            'type' => $type,
            'enabled' => $enabled,
            'variant' => [],
            'data' => $data,
        ];
    }

    /** Nothing to convert on a database that has no homepage content yet. */
    private static function hasLegacyContent(array $rows): bool
    {
        foreach (['hero_title', 'hero_subtitle', 'features', 'testimonials', 'pricing_plans', 'cta_title'] as $key) {
            if (array_key_exists($key, $rows)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stored order leads; anything it forgot is appended, exactly the way
     * LandingController already normalises it.
     *
     * @return array<int,string>
     */
    private static function order(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];

        $kept = array_values(array_filter(
            $stored,
            fn ($s) => is_string($s) && in_array($s, self::SECTIONS, true),
        ));

        return array_values(array_unique([...$kept, ...self::SECTIONS]));
    }

    /**
     * @param  array<int,string>  $stringKeys
     * @param  array<int,string>  $boolKeys
     * @return array<int,array<string,mixed>>
     */
    private static function rows(mixed $value, array $stringKeys, array $boolKeys = []): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = [];

            foreach ($stringKeys as $key) {
                $mapped[$key] = self::str($row[$key] ?? '');
            }

            foreach ($boolKeys as $key) {
                $mapped[$key] = self::bool($row[$key] ?? false);
            }

            $out[] = $mapped;
        }

        return $out;
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value)
            ? (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false)
            : false;
    }
}
