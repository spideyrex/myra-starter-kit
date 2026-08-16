<?php

namespace Tests\Feature\Resource;

use App\Models\MyraSiteIdentity;
use App\Models\User;
use Tests\TestCase;

class SingularResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function demoUser(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo('demo.view');

        return $user->fresh();
    }

    public function test_it_renders_on_an_empty_table_with_null_values(): void
    {
        $this->actingAs($this->demoUser());

        $response = $this->get(route('admin.learning.site-identity.show'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertFalse($props['exists']);
        $this->assertSame(['name' => null, 'tagline' => null], $props['record']);
        $this->assertSame('learning.site-identity', $props['singularKey']);
        $this->assertTrue($props['canEdit']);
    }

    public function test_the_first_save_creates_the_record(): void
    {
        $this->actingAs($this->demoUser());

        $this->put(route('admin.learning.site-identity.update'), [
            'name' => 'Myra',
            'tagline' => 'Admin, assembled.',
        ])->assertRedirect();

        $this->assertSame(1, MyraSiteIdentity::query()->count());
        $this->assertDatabaseHas('myra_site_identity', ['name' => 'Myra']);
    }

    public function test_a_second_save_updates_the_same_row(): void
    {
        $this->actingAs($this->demoUser());

        $this->put(route('admin.learning.site-identity.update'), ['name' => 'First', 'tagline' => null]);
        $this->put(route('admin.learning.site-identity.update'), ['name' => 'Second', 'tagline' => null]);

        $this->assertSame(1, MyraSiteIdentity::query()->count());
        $this->assertSame('Second', MyraSiteIdentity::query()->first()->name);
    }

    public function test_the_saved_values_come_back_on_the_next_show(): void
    {
        $this->actingAs($this->demoUser());

        $this->put(route('admin.learning.site-identity.update'), ['name' => 'Myra', 'tagline' => 'Tag']);

        $props = $this->get(route('admin.learning.site-identity.show'))->viewData('page')['props'];

        $this->assertTrue($props['exists']);
        $this->assertSame(['name' => 'Myra', 'tagline' => 'Tag'], $props['record']);
    }

    public function test_validation_failure_returns_errors_and_writes_nothing(): void
    {
        $this->actingAs($this->demoUser());

        $this->from(route('admin.learning.site-identity.show'))
            ->put(route('admin.learning.site-identity.update'), ['name' => str_repeat('x', 300)])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, MyraSiteIdentity::query()->count());
    }

    public function test_there_is_no_create_and_no_destroy_route(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.learning.site-identity.create'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.learning.site-identity.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.learning.site-identity.destroy'));
    }

    public function test_both_routes_are_gated(): void
    {
        $this->actingAs($this->makeUser());

        $this->get(route('admin.learning.site-identity.show'))->assertForbidden();
        $this->put(route('admin.learning.site-identity.update'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_the_payload_exposes_only_the_declared_rule_keys(): void
    {
        $this->actingAs($this->demoUser());

        MyraSiteIdentity::query()->create(['name' => 'Myra', 'tagline' => 'Tag']);

        $props = $this->get(route('admin.learning.site-identity.show'))->viewData('page')['props'];

        // No id, no timestamps: the rule keys are the whitelist.
        $this->assertSame(['name', 'tagline'], array_keys($props['record']));
    }
}
