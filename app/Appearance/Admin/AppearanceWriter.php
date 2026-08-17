<?php

namespace App\Appearance\Admin;

use App\Brand\BrandManager;
use Illuminate\Support\Facades\DB;

/**
 * The admin write seam. Rows are addressed by NAME, never through a typed
 * settings property, so the editor keeps working while the engine bundle is
 * absent. 'appearance' is already in BrandManager::GROUPS, so forget() plus the
 * version probe is the whole invalidation story.
 */
final class AppearanceWriter
{
    public const GROUP = 'appearance';

    /** The fourteen rows, name => default. Normative; nobody invents a fifteenth. */
    public const DEFAULTS = [
        'auth_layout' => 'split',
        'auth_flip' => false,
        'auth_show_tagline' => true,
        'auth_bg_type' => 'brand',
        'auth_bg_color' => null,
        'auth_bg_recipe' => null,
        'auth_bg_image_path' => null,
        'auth_bg_scrim' => 'medium',
        'page_bg_type' => 'none',
        'page_bg_color' => null,
        'page_bg_recipe' => null,
        'page_bg_image_path' => null,
        'page_bg_scrim' => 'medium',
        'page_navbar_translucent' => false,
    ];

    /** B-local allowlists. Pinned to the spec, not imported from App\Appearance. */
    public const LAYOUTS = ['split', 'centered', 'cover', 'card'];

    public const TYPES = ['brand', 'solid', 'gradient', 'pattern', 'image', 'none'];

    public const GRADIENTS = ['brand-fade', 'brand-mesh', 'dusk', 'dawn', 'ink', 'aurora'];

    public const PATTERNS = ['dots', 'grid', 'diagonal', 'noise'];

    public const SCRIMS = ['none', 'light', 'medium', 'strong'];

    public const SURFACES = ['auth', 'page'];

    /** Every row, defaults filled in. Total — a missing or corrupt row is the default. */
    public function read(): array
    {
        $stored = [];

        try {
            $rows = DB::table('settings')
                ->where('group', self::GROUP)
                ->whereIn('name', array_keys(self::DEFAULTS))
                ->pluck('payload', 'name');

            foreach ($rows as $name => $payload) {
                $value = json_decode((string) $payload, true);
                $stored[$name] = $value;
            }
        } catch (\Throwable) {
            $stored = [];
        }

        $out = [];

        foreach (self::DEFAULTS as $name => $default) {
            $value = $stored[$name] ?? null;
            $out[$name] = is_scalar($value) ? $value : $default;
        }

        return $out;
    }

    /** Writes only known names. Unknown keys are dropped, never persisted. */
    public function write(array $values): void
    {
        foreach ($values as $name => $value) {
            if (! array_key_exists($name, self::DEFAULTS)) {
                continue;
            }

            $where = ['group' => self::GROUP, 'name' => $name];
            $row = ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now()];

            if (! DB::table('settings')->where($where)->exists()) {
                $row['created_at'] = now();
            }

            DB::table('settings')->updateOrInsert($where, $row);
        }

        app(BrandManager::class)->forget();
    }

    /** @return array<int,string> the row names owned by one surface */
    public static function namesFor(string $surface): array
    {
        return array_values(array_filter(
            array_keys(self::DEFAULTS),
            static fn (string $name) => str_starts_with($name, $surface.'_'),
        ));
    }
}
