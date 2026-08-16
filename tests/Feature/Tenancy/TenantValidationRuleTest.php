<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/** A real FormRequest on a real route — a hand-built Validator proves less. */
class TenantScopedCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Tenancy::unique('categories', 'slug')],
        ];
    }
}

class TenantValidationRuleTest extends TestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamA = Team::create(['name' => 'Alpha']);
        $this->teamB = Team::create(['name' => 'Beta']);

        config([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Category::class],
        ]);
        Tenancy::flush();
        Model::clearBootedModels();

        Route::middleware('web')->post(
            '/__test/tenant-category',
            fn (TenantScopedCategoryRequest $request) => response()->json(['ok' => true]),
        );
    }

    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function memberOf(Team $team): User
    {
        $user = $this->makeUser();
        $user->teams()->attach($team->id);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_two_tenants_may_hold_the_same_unique_value(): void
    {
        $a = $this->memberOf($this->teamA);
        $b = $this->memberOf($this->teamB);

        Tenancy::for($this->teamA, fn () => Category::factory()->create([
            'name' => 'News', 'slug' => 'news', 'created_by' => $a->id,
        ]));

        $this->assertTrue(Category::withoutGlobalScopes()->where('slug', 'news')->exists());

        $this->actingAs($b);
        Tenancy::flush();

        // Plain Rule::unique would have rejected this.
        $this->postJson('/__test/tenant-category', ['name' => 'News', 'slug' => 'news'])
            ->assertOk();
    }

    public function test_the_same_tenant_still_collides(): void
    {
        $a = $this->memberOf($this->teamA);

        Tenancy::for($this->teamA, fn () => Category::factory()->create([
            'name' => 'News', 'slug' => 'news', 'created_by' => $a->id,
        ]));

        $this->actingAs($a);
        Tenancy::flush();

        $this->postJson('/__test/tenant-category', ['name' => 'News', 'slug' => 'news'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_rules_degrade_to_the_plain_rules_when_disabled(): void
    {
        config(['myra.tenancy.enabled' => false]);
        Tenancy::flush();

        $this->assertSame(
            (string) Rule::unique('categories', 'slug'),
            (string) Tenancy::unique('categories', 'slug'),
        );
    }
}
