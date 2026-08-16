<?php

namespace Tests\Feature\Plugin;

use App\Admin\Plugin\Exceptions\PluginException;
use App\Admin\Plugin\PluginRegistry;
use App\Plugins\Example\ExamplePlugin;
use Tests\Feature\Plugin\Fixtures\AlphaPlugin;
use Tests\Feature\Plugin\Fixtures\BetaPlugin;
use Tests\Feature\Plugin\Fixtures\FuturePlugin;
use Tests\TestCase;

class PluginRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        // The registry is static: leave it empty so the next test's boot
        // reloads the real config-declared list.
        PluginRegistry::flush();

        parent::tearDown();
    }

    /** Re-run the loader against a bespoke plugin list. */
    private function reload(array $plugins, ?bool $strict = false): void
    {
        config(['myra.extensions.plugins' => $plugins, 'myra.extensions.strict' => $strict]);
        PluginRegistry::flush();
        PluginRegistry::load();
    }

    public function test_declared_plugin_is_loaded_and_manifest_applied(): void
    {
        $this->assertTrue(PluginRegistry::has('myra-example'));
        $this->assertInstanceOf(ExamplePlugin::class, PluginRegistry::get('myra-example'));

        // register() deep-merged the manifest's modules into the RBAC map.
        $this->assertSame(['view'], config('shield.modules')['myra-example']);

        // The merge is additive: the core modules survived.
        $this->assertSame(['view', 'create', 'edit', 'delete'], config('shield.modules')['users']);
    }

    public function test_duplicate_id_is_quarantined_and_the_admin_stays_up(): void
    {
        $this->reload([AlphaPlugin::class, BetaPlugin::class], strict: false);

        $this->assertTrue(PluginRegistry::has('duplicated-id'));
        $this->assertInstanceOf(AlphaPlugin::class, PluginRegistry::get('duplicated-id'));
        $this->assertArrayHasKey(BetaPlugin::class, PluginRegistry::failed());
        $this->assertStringContainsString('Duplicate plugin id', PluginRegistry::failed()[BetaPlugin::class]->getMessage());

        $this->actingAsAdmin();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_duplicate_id_throws_under_strict(): void
    {
        $this->expectException(PluginException::class);

        $this->reload([AlphaPlugin::class, BetaPlugin::class], strict: true);
    }

    public function test_missing_class_is_quarantined(): void
    {
        $this->reload(['App\\Plugins\\Nope\\NopePlugin'], strict: false);

        $this->assertSame([], PluginRegistry::loaded());
        $this->assertArrayHasKey('App\\Plugins\\Nope\\NopePlugin', PluginRegistry::failed());
    }

    public function test_min_version_below_current_fails(): void
    {
        $this->reload([FuturePlugin::class], strict: false);

        $this->assertFalse(PluginRegistry::has('from-the-future'));
        $this->assertStringContainsString('requires Myra >= 99.0.0', PluginRegistry::failed()[FuturePlugin::class]->getMessage());
    }

    public function test_strict_is_off_by_default_so_a_broken_plugin_never_takes_the_admin_down(): void
    {
        config(['myra.extensions.strict' => null]);
        $this->assertFalse(PluginRegistry::strict());

        config(['myra.extensions.strict' => true]);
        $this->assertTrue(PluginRegistry::strict());
    }

    public function test_the_shipped_default_quarantines_a_broken_plugin(): void
    {
        // No strict override at all: the config default is what production runs.
        config(['myra.extensions.plugins' => ['App\\Plugins\\Nope\\NopePlugin']]);
        PluginRegistry::flush();
        PluginRegistry::load();

        $this->assertArrayHasKey('App\\Plugins\\Nope\\NopePlugin', PluginRegistry::failed());

        $this->actingAsAdmin();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_plugin_config_is_read_without_an_accessor(): void
    {
        config(['myra.plugin_config.myra-example.greeting' => 'hello']);

        $this->assertSame('hello', ExamplePlugin::config('greeting'));
        $this->assertSame('fallback', ExamplePlugin::config('missing', 'fallback'));
    }
}
