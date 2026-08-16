<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additive. `enabled` is false and every value is SEEDED from the existing
 * general/appearance rows, so an upgraded install is byte-identical on day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = fn (string $group, string $name, $default = null) => $this->value($group, $name, $default);

        $brand = [
            'enabled' => false,
            'name' => (string) ($existing('general', 'site_name') ?: config('app.name', 'Admin')),
            'short_name' => null,
            'tagline' => (string) ($existing('general', 'login_tagline') ?: ''),
            'description' => (string) ($existing('general', 'site_description') ?: ''),
            'logo_path' => $existing('appearance', 'logo_path'),
            'logo_dark_path' => null,
            'mark_path' => null,
            'favicon_path' => $existing('appearance', 'favicon_path'),
            'og_image_path' => null,
            'logo_position' => (string) ($existing('appearance', 'logo_position') ?: 'header'),
            'primary' => (string) ($existing('appearance', 'primary_color') ?: '#18181b'),
            'accent' => null,
            'sidebar_background' => $existing('appearance', 'sidebar_background'),
            'sidebar_foreground' => $existing('appearance', 'sidebar_foreground'),
            'sidebar_accent' => $existing('appearance', 'sidebar_accent'),
            'preset' => (string) ($existing('appearance', 'theme') ?: 'zinc'),
            'font_sans' => 'figtree',
            'font_mono' => 'ui-monospace',
            'radius' => '0.625rem',
            'dark_default' => false,
            'version' => 1,
        ];

        foreach ($brand as $name => $payload) {
            $this->put('brand', $name, $payload);
        }

        // Promote the two silently-dropped SEO fields to real properties.
        $this->put('seo', 'og_image_path', null);
        $this->put('seo', 'robots_txt', null);
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'brand')->delete();

        DB::table('settings')
            ->where('group', 'seo')
            ->whereIn('name', ['og_image_path', 'robots_txt'])
            ->delete();
    }

    private function put(string $group, string $name, $payload): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'name' => $name],
            ['payload' => json_encode($payload), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function value(string $group, string $name, $default = null)
    {
        $raw = DB::table('settings')->where('group', $group)->where('name', $name)->value('payload');

        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode((string) $raw, true);

        return $decoded === null ? $default : $decoded;
    }
};
