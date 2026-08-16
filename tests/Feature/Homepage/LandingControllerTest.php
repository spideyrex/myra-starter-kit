<?php

namespace Tests\Feature\Homepage;

use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Tests\TestCase;

class LandingControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    public function test_the_chooser_is_gated_by_settings_edit(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.landing.index'))->assertForbidden();
        $this->put(route('admin.landing.update'), ['template' => 'minimal'])->assertForbidden();
    }

    public function test_the_chooser_ships_the_real_registry_payload(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.landing.index'))->assertOk()->viewData('page')['props'];

        $this->assertCount(count(TemplateRegistry::ids()), $props['templates']);
        $this->assertSame(TemplateRegistry::ids(), array_column($props['templates'], 'key'));
        $this->assertSame('classic', $props['current']);
        $this->assertSame(
            ['hero', 'features', 'testimonials', 'pricing', 'cta'],
            $props['sections'],
        );
    }

    public function test_saving_a_template_changes_what_the_public_homepage_renders(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.landing.update'), [
            'template' => 'docs',
            'section_order' => ['features', 'hero', 'pricing', 'cta', 'testimonials'],
        ])->assertRedirect();

        $this->assertSame('docs', app(HomepageSettings::class)->template);

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame('docs', $props['template']);
        $this->assertSame(['features', 'hero', 'pricing', 'cta'], $props['sectionOrder']);
    }

    public function test_an_unregistered_template_is_refused_by_validation(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.landing.update'), [
            'template' => 'not-a-template',
            'section_order' => ['hero'],
        ])->assertSessionHasErrors('template');

        $this->assertSame('classic', app(HomepageSettings::class)->template);
    }

    public function test_an_unknown_section_is_refused_by_validation(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.landing.update'), [
            'template' => 'classic',
            'section_order' => ['hero', 'drop table'],
        ])->assertSessionHasErrors('section_order.1');
    }

    public function test_a_partial_section_order_is_normalised_rather_than_dropping_sections(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.landing.update'), [
            'template' => 'classic',
            'section_order' => ['cta', 'cta', 'hero'],
        ])->assertRedirect();

        $this->assertSame(
            ['cta', 'hero', 'features', 'testimonials', 'pricing'],
            app(HomepageSettings::class)->section_order,
        );
    }
}
