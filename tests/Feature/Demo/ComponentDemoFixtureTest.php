<?php

namespace Tests\Feature\Demo;

use App\Homepage\TemplateRegistry;
use Tests\TestCase;

/**
 * The bridge between the v2.6 demo controller and the vitest specs.
 *
 * tests/js/newComponentDemos.a11y.spec.ts mounts the committed fixture, so the
 * a11y baseline runs against the payload the server actually emits rather than
 * a hand-authored stand-in. A drifting controller fails HERE first.
 */
class ComponentDemoFixtureTest extends TestCase
{
    use SyncsDemoFixtures;

    /** @var array<string,array{0:string,1:array<int,string>}> */
    private const DEMOS = [
        'emptyAndItem' => ['admin.demo.empty-and-item', ['projects']],
        'chartPrimitives' => ['admin.demo.chart-primitives', ['monthly', 'channels']],
        'otpAndCombobox' => ['admin.demo.otp-and-combobox', ['timezones']],
        'conversation' => ['admin.demo.conversation', ['thread', 'attachments']],
        'questionnaire' => ['admin.demo.questionnaire', ['questions']],
        'mapMarkers' => ['admin.demo.map-markers', ['markers']],
        'landingTemplates' => ['admin.demo.landing-templates', ['templates']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    public function test_the_component_demo_props_match_the_committed_fixture(): void
    {
        $this->actingAsSuperAdmin();

        $payload = [];

        foreach (self::DEMOS as $key => [$route, $propNames]) {
            $props = $this->get(route($route))->assertOk()->viewData('page')['props'];

            $payload[$key] = array_intersect_key($props, array_flip($propNames));

            foreach ($propNames as $name) {
                $this->assertArrayHasKey($name, $payload[$key], "Demo [{$key}] stopped shipping [{$name}].");
            }
        }

        $this->syncFixture('tests/js/fixtures/component-demos.json', $payload);
    }
}
