<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AppearanceManager;
use App\Brand\BrandManager;
use Illuminate\Support\Facades\DB;

/**
 * Writes the REAL settings rows an operator (or bundle B's writer) would write,
 * then clears the resolver cache. Nothing here hand-authors a payload.
 */
trait WritesAppearanceSettings
{
    /** @param array<string,mixed> $values */
    protected function writeAppearance(array $values): void
    {
        foreach ($values as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($payload), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->forgetAppearance();
    }

    /** @param array<int,string> $names */
    protected function deleteAppearance(array $names): void
    {
        DB::table('settings')->where('group', 'appearance')->whereIn('name', $names)->delete();

        $this->forgetAppearance();
    }

    /** @return array<int,string> the fourteen v2.8 row names */
    protected function surfaceRowNames(): array
    {
        return [
            'auth_layout', 'auth_flip', 'auth_show_tagline', 'auth_bg_type', 'auth_bg_color',
            'auth_bg_recipe', 'auth_bg_image_path', 'auth_bg_scrim',
            'page_bg_type', 'page_bg_color', 'page_bg_recipe', 'page_bg_image_path',
            'page_bg_scrim', 'page_navbar_translucent',
        ];
    }

    protected function forgetAppearance(): void
    {
        app(BrandManager::class)->forget();
        app(AppearanceManager::class)->forget();
    }

    /** GuestLayout plus every shell it can dispatch to, as raw text. */
    protected function guestShellSource(): string
    {
        $files = array_merge(
            [resource_path('js/Layouts/GuestLayout.vue')],
            glob(resource_path('js/Layouts/Guest/*.vue')) ?: [],
        );

        return implode("\n", array_map(static fn (string $f) => (string) file_get_contents($f), $files));
    }

    /** The `myra-appearance` meta payload, parsed out of a real response body. */
    protected function appearanceMetaFrom(string $html): array
    {
        $matched = preg_match('/<meta name="myra-appearance" content="([^"]*)">/', $html, $m);

        $this->assertSame(1, $matched, 'The myra-appearance meta tag is missing from the response.');

        $decoded = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertIsArray($decoded, 'The myra-appearance meta tag did not carry valid JSON.');

        return $decoded;
    }
}
