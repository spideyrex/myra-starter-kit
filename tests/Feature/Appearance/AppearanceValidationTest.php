<?php

namespace Tests\Feature\Appearance;

use App\Appearance\Admin\AppearanceWriter;
use App\Appearance\Admin\AuthPreviewSlot;
use App\Brand\BrandManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 7.11 — every authored value is an allowlisted choice, and anything that gets
 * past validation by another route is stripped before it can reach CSS.
 */
class AppearanceValidationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'auth_layout' => 'split',
            'auth_flip' => false,
            'auth_show_tagline' => true,
            'auth_bg_type' => 'brand',
            'auth_bg_color' => null,
            'auth_bg_recipe' => null,
            'auth_bg_scrim' => 'medium',
        ], $overrides);
    }

    private function stored(string $name): mixed
    {
        $row = DB::table('settings')
            ->where('group', AppearanceWriter::GROUP)
            ->where('name', $name)
            ->value('payload');

        return $row === null ? null : json_decode((string) $row, true);
    }

    public function test_a_valid_payload_is_accepted(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload([
            'auth_layout' => 'cover',
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'dusk',
            'auth_bg_scrim' => 'strong',
        ]))->assertRedirect();

        $this->assertSame('cover', $this->stored('auth_layout'));
        $this->assertSame('dusk', $this->stored('auth_bg_recipe'));
    }

    public function test_an_unknown_layout_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload(['auth_layout' => 'nope']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('auth_layout');
    }

    public function test_an_unknown_background_type_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload(['auth_bg_type' => 'video']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('auth_bg_type');
    }

    public function test_an_unknown_recipe_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload([
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'linear-gradient(red,blue)',
        ]))->assertStatus(422)->assertJsonValidationErrors('auth_bg_recipe');
    }

    public function test_a_recipe_from_the_wrong_family_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload([
            'auth_bg_type' => 'pattern',
            'auth_bg_recipe' => 'aurora',
        ]))->assertStatus(422)->assertJsonValidationErrors('auth_bg_recipe');
    }

    public function test_an_unknown_scrim_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson(route('admin.appearance.update'), $this->payload(['auth_bg_scrim' => 'opaque']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('auth_bg_scrim');
    }

    public function test_a_raw_css_string_in_the_colour_field_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['rgb(1,2,3)', 'var(--primary)', 'url(https://evil.test/x.png)', 'red'] as $hostile) {
            $this->putJson(route('admin.appearance.update'), $this->payload([
                'auth_bg_type' => 'solid',
                'auth_bg_color' => $hostile,
            ]))->assertStatus(422)->assertJsonValidationErrors('auth_bg_color');
        }
    }

    public function test_an_image_path_is_never_accepted_from_the_update_form(): void
    {
        $this->actingAsSuperAdmin();

        app(AppearanceWriter::class)->write(['auth_bg_image_path' => 'appearance/abc12345/auth.png']);

        $this->putJson(route('admin.appearance.update'), $this->payload([
            'auth_bg_type' => 'image',
            'auth_bg_image_path' => '../../../.env',
        ]))->assertRedirect();

        $this->assertSame('appearance/abc12345/auth.png', $this->stored('auth_bg_image_path'));
    }

    public function test_a_hostile_hex_fails_validation_and_is_stripped_if_forced_into_the_table(): void
    {
        $this->actingAsSuperAdmin();

        $hostile = '#fff");}body{display:none';

        $this->putJson(route('admin.appearance.update'), $this->payload([
            'auth_bg_type' => 'solid',
            'auth_bg_color' => $hostile,
        ]))->assertStatus(422)->assertJsonValidationErrors('auth_bg_color');

        // Forced past the form, straight into the settings table.
        app(AppearanceWriter::class)->write([
            'auth_bg_type' => 'solid',
            'auth_bg_color' => $hostile,
        ]);
        app(BrandManager::class)->forget();

        $surface = AuthPreviewSlot::surface(app(AppearanceWriter::class)->read(), 'auth');
        $emitted = implode(' ', array_values((array) $surface['css_vars']));

        foreach (['display', 'body', '"', ';', '{', '}', '#', ':'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $emitted);
        }

        $this->assertNotSame('', (string) (((array) $surface['css_vars'])['--myra-auth-bg'] ?? ''));
    }

    public function test_the_update_is_gated_by_brand_update(): void
    {
        $this->actingAsUser();

        $this->putJson(route('admin.appearance.update'), $this->payload())->assertForbidden();
    }

    public function test_the_page_is_gated_by_brand_view(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.appearance.index'))->assertForbidden();
    }

    public function test_a_super_admin_can_open_the_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.appearance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Appearance/Index')
                ->has('appearanceSettings')
                ->has('layouts')
                ->has('options')
                ->has('preview.surface.base'));
    }
}
