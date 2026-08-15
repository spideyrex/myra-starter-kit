<?php

namespace Tests\Feature\Report;

use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRunner;
use App\Admin\Report\ReportShape;
use App\Models\TableView;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportViewTest extends TestCase
{
    private const KEY = 'report:users';

    private function state(): array
    {
        return [
            'period' => ['preset' => 'last_7_days'],
            'compare' => 'previous',
            'dimension' => 'status',
            'measures' => ['signups'],
            'limit' => 5,
            'chart' => 'bar',
        ];
    }

    public function test_saving_a_report_state_creates_a_prefixed_table_view(): void
    {
        $user = $this->actingAsSuperAdmin();

        $this->post(route('admin.table-views.store'), [
            'table_key' => self::KEY,
            'name' => 'Weekly by status',
            'visibility' => 'private',
            'is_default' => false,
            'payload' => $this->state(),
        ])->assertRedirect();

        $view = TableView::where('table_key', self::KEY)->firstOrFail();

        $this->assertSame($user->id, (int) $view->user_id);
        $this->assertSame('status', $view->payload['dimension']);
    }

    public function test_a_saved_state_round_trips_through_report_request_parse(): void
    {
        $actor = $this->actingAsSuperAdmin();
        User::factory()->count(4)->create();

        $definition = ReportRegistry::resolve('users');
        $runner = new ReportRunner($definition);

        $first = $runner->run(ReportRequest::parse($this->state(), $definition, $actor), $actor);

        $replayed = $runner->run(
            ReportRequest::parse($first->toArray()['state'], $definition, $actor),
            $actor,
        );

        $this->assertSame($first->totals(), $replayed->totals());
        $this->assertSame(
            array_map(fn ($r) => $r->key, $first->rows()),
            array_map(fn ($r) => $r->key, $replayed->rows()),
        );
    }

    public function test_a_team_view_is_visible_to_a_teammate_and_hidden_from_a_stranger(): void
    {
        $team = Team::create(['name' => 'Reporting '.uniqid()]);

        $owner = $this->makeUser(['current_team_id' => $team->id]);
        $mate = $this->makeUser(['current_team_id' => $team->id]);
        $stranger = $this->makeUser();

        $team->users()->attach([$owner->id, $mate->id]);

        $view = new TableView([
            'table_key' => self::KEY,
            'name' => 'Team weekly',
            'payload' => $this->state(),
            'visibility' => 'team',
            'is_default' => false,
            'team_id' => $team->id,
            'sort' => 0,
        ]);
        $view->user_id = $owner->id;
        $view->slug = (string) \Illuminate\Support\Str::ulid();
        $view->save();

        $this->assertTrue(TableView::visibleTo($mate)->where('table_key', self::KEY)->exists());
        $this->assertFalse(TableView::visibleTo($stranger)->where('table_key', self::KEY)->exists());
    }

    public function test_report_shape_rejects_an_unknown_top_level_key(): void
    {
        $this->expectException(ValidationException::class);

        ReportShape::assertState(['dimension' => 'status', 'evil' => 1], 'payload');
    }

    public function test_report_shape_rejects_too_many_cross_filters(): void
    {
        $cross = [];
        for ($i = 0; $i < 9; $i++) {
            $cross['w' . $i] = ['conjunction' => 'and', 'rules' => [], 'groups' => []];
        }

        $this->expectException(ValidationException::class);

        ReportShape::assertState(['cross' => $cross], 'payload');
    }

    public function test_report_shape_accepts_a_canonical_state(): void
    {
        ReportShape::assertState($this->state(), 'payload');

        $this->assertTrue(true);
    }
}
