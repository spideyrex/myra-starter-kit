<?php

namespace Tests\Feature\Homepage;

use App\Homepage\PageSurfaceFallback;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The page surface sits on the template ROOT, above the legacy/page-builder
 * branch inside TemplateBody — so one implementation covers both paths and the
 * payload cannot differ between them.
 */
class PageSurfaceParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    /** @return array<string,mixed> */
    private function homeProps(): array
    {
        return $this->get('/')->assertOk()->viewData('page')['props'];
    }

    private function writeRows(array $rows): void
    {
        foreach ($rows as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now()],
            );
        }

        app(\App\Brand\BrandManager::class)->forget();
    }

    public function test_the_same_surface_reaches_the_legacy_and_the_block_path(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->blocks = [];
        $settings->save();

        $legacy = $this->homeProps();
        $this->assertSame([], $legacy['blocks'], 'An empty block list is the legacy path.');

        $settings = app(HomepageSettings::class);
        $settings->blocks = [
            ['id' => 'b-hero', 'type' => 'hero', 'variant' => [], 'data' => ['title' => 'Built']],
        ];
        $settings->save();

        $built = $this->homeProps();
        $this->assertNotSame([], $built['blocks'], 'A non-empty block list is the builder path.');

        $this->assertEquals(
            $legacy['surface'],
            $built['surface'],
            'The surface is above the legacy/builder branch, so both paths must receive it identically.',
        );
        $this->assertSame($legacy['navbarTranslucent'], $built['navbarTranslucent']);
    }

    public function test_the_default_page_surface_is_inert(): void
    {
        $props = $this->homeProps();

        $this->assertSame('none', $props['surface']['type']);
        $this->assertFalse($props['navbarTranslucent']);
        $this->assertSame(
            [],
            (array) $props['surface']['css_vars'],
            'The default surface emits no CSS at all, so the templates render exactly what they always did.',
        );
        $this->assertSame(PageSurfaceFallback::inert()['type'], $props['surface']['type']);
    }

    /**
     * The engine ships in another bundle. Absent, it must resolve to the inert
     * surface rather than take the public homepage down.
     */
    public function test_the_homepage_renders_without_the_appearance_engine(): void
    {
        if (class_exists(\App\Appearance\AppearanceManager::class)) {
            $this->markTestSkipped('The appearance engine is installed; this case covers its absence.');
        }

        $this->writeRows([
            'page_bg_type' => 'gradient',
            'page_bg_recipe' => 'dusk',
            'page_navbar_translucent' => true,
        ]);

        $props = $this->homeProps();

        $this->assertSame('none', $props['surface']['type']);
        $this->assertFalse($props['navbarTranslucent']);
    }

    public function test_hostile_stored_rows_never_take_the_homepage_down(): void
    {
        $this->writeRows([
            'page_bg_type' => ['not', 'a', 'string'],
            'page_bg_color' => '#fff");}body{display:none',
            'page_bg_recipe' => '../../etc/passwd',
            'page_bg_scrim' => 999,
            'page_navbar_translucent' => 'yes please',
        ]);

        $props = $this->homeProps();

        $this->assertArrayHasKey('surface', $props);
        $this->assertIsString($props['surface']['type']);
    }

    public function test_the_landing_form_round_trips_the_real_payload(): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame('none', $before['surface']['page_bg_type']);
        $this->assertContains('gradient', $before['surfaceOptions']['types']);

        // Exactly what Index.vue's form sends: the props it was handed, with the
        // background card's choices applied and its transform() rules obeyed.
        $payload = [
            'template' => $before['current'],
            'section_order' => $before['sectionOrder'],
            'page_bg_type' => 'gradient',
            'page_bg_color' => '#101828',
            'page_bg_recipe' => $before['surfaceOptions']['gradients'][0],
            'page_bg_scrim' => $before['surface']['page_bg_scrim'],
            'page_navbar_translucent' => true,
        ];

        $this->put(route('admin.landing.update'), $payload)->assertRedirect();

        $after = $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame('gradient', $after['surface']['page_bg_type']);
        $this->assertSame('#101828', $after['surface']['page_bg_color']);
        $this->assertSame($payload['page_bg_recipe'], $after['surface']['page_bg_recipe']);
        $this->assertTrue($after['surface']['page_navbar_translucent']);
    }

    /** @return array<string,mixed> */
    private function storedSurface(): array
    {
        return $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props']['surface'];
    }

    public function test_a_template_only_save_leaves_the_surface_untouched(): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->storedSurface();

        $this->put(route('admin.landing.update'), [
            'template' => 'minimal',
            'section_order' => ['hero'],
        ])->assertRedirect();

        $this->assertSame(
            $before,
            $this->storedSurface(),
            'A client that predates the background card must leave the surface exactly as it was.',
        );
    }

    /** @return array<string,array{0:string,1:mixed}> */
    public static function refusedValues(): array
    {
        return [
            'unknown type' => ['page_bg_type', 'video'],
            'traversal type' => ['page_bg_type', '../../etc'],
            'unknown recipe' => ['page_bg_recipe', 'not-a-recipe'],
            'raw css colour' => ['page_bg_color', '#fff");}body{display:none'],
            'named colour' => ['page_bg_color', 'red'],
            'gradient string colour' => ['page_bg_color', 'linear-gradient(red,blue)'],
            'unknown scrim' => ['page_bg_scrim', 'heavy'],
        ];
    }

    /** @dataProvider refusedValues */
    public function test_an_out_of_allowlist_value_is_refused(string $field, mixed $value): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->storedSurface();

        $this->put(route('admin.landing.update'), [
            'template' => 'classic',
            'section_order' => ['hero'],
            $field => $value,
        ])->assertSessionHasErrors($field);

        $this->assertSame($before, $this->storedSurface());
    }

    public function test_the_image_path_is_never_written_by_this_form(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.landing.update'), [
            'template' => 'classic',
            'section_order' => ['hero'],
            'page_bg_type' => 'image',
            'page_bg_image_path' => '../../../.env',
        ])->assertRedirect();

        $row = DB::table('settings')->where('group', 'appearance')->where('name', 'page_bg_image_path')->first();

        $this->assertTrue(
            $row === null || json_decode((string) $row->payload, true) === null,
            'Uploads are the only way an image path is ever stored.',
        );
    }

    public function test_a_recipe_from_the_wrong_family_is_dropped_rather_than_stored(): void
    {
        $this->actingAsSuperAdmin();

        // 'dots' is a pattern; the chosen type is a gradient.
        $this->put(route('admin.landing.update'), [
            'template' => 'classic',
            'section_order' => ['hero'],
            'page_bg_type' => 'gradient',
            'page_bg_recipe' => 'dots',
        ])->assertRedirect();

        $props = $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame('gradient', $props['surface']['page_bg_type']);
        $this->assertNull($props['surface']['page_bg_recipe']);
    }

    public function test_the_admin_page_normalises_hostile_rows_before_it_ships_them(): void
    {
        $this->actingAsSuperAdmin();

        $this->writeRows([
            'page_bg_type' => 'javascript:alert(1)',
            'page_bg_color' => 'url(https://evil.test/x.png)',
            'page_bg_recipe' => ['dusk'],
            'page_bg_scrim' => null,
            'page_navbar_translucent' => 'true',
        ]);

        $props = $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame('none', $props['surface']['page_bg_type']);
        $this->assertNull($props['surface']['page_bg_color']);
        $this->assertNull($props['surface']['page_bg_recipe']);
        $this->assertSame('medium', $props['surface']['page_bg_scrim']);
    }
}
