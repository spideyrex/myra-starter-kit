<?php

namespace Tests\Feature\Plugin;

use Tests\TestCase;

/**
 * REAL PAYLOAD: hits the real demo route through the real kernel, pulls the
 * real Inertia props, and commits them as the fixture tests/js/pluginDemo.spec
 * mounts the real Vue page against.
 */
class PluginDemoPropsTest extends TestCase
{
    use SyncsPluginFixtures;

    public function test_the_demo_page_props_match_the_committed_fixture(): void
    {
        // Pin the failure mode so the payload does not depend on the ambient env.
        config(['myra.extensions.strict' => true]);

        $this->actingAsSuperAdmin();

        $response = $this->get(route('admin.demo.plugins'))->assertOk();

        $props = $response->viewData('page')['props'];

        $this->syncFixture('tests/js/fixtures/plugin-demo.json', [
            'plugins' => $props['plugins'],
            'failed' => $props['failed'],
            'strict' => $props['strict'],
        ]);
    }

    public function test_the_payload_describes_the_real_example_plugin(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.demo.plugins'))->assertOk()->viewData('page')['props'];

        $this->assertSame('myra-example', $props['plugins'][0]['id']);
        $this->assertSame(\App\Plugins\Example\ExamplePlugin::class, $props['plugins'][0]['class']);
        $this->assertSame(1, $props['plugins'][0]['routes']);
        $this->assertSame([], $props['failed']);
    }

    public function test_the_demo_page_is_gated_by_the_demo_permission(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.demo.plugins'))->assertForbidden();
    }
}
