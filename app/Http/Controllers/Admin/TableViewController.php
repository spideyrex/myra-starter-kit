<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Views\ViewShape;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TableViewRequest;
use App\Models\TableView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TableView::class);

        $validated = $request->validate([
            'table_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._:-]*$/i'],
        ]);

        $user = $request->user();

        $views = TableView::visibleTo($user)
            ->where('table_key', $validated['table_key'])
            ->where('name', '!=', TableView::COLUMNS_NAME)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (TableView $view) => $view->toClientArray($user))
            ->values();

        return response()->json(['views' => $views]);
    }

    public function store(TableViewRequest $request): RedirectResponse
    {
        Gate::authorize('create', TableView::class);

        $data = $request->validated();
        $user = $request->user();

        $this->assertQueryShape($data);
        $this->assertTeamVisibility($data, $user);

        $count = TableView::where('user_id', $user->id)
            ->where('table_key', $data['table_key'])
            ->count();

        if ($count >= (int) config('myra.views.max', 25)) {
            throw ValidationException::withMessages([
                'name' => __('You have reached the saved view limit for this table.'),
            ]);
        }

        DB::transaction(function () use ($data, $user) {
            $view = new TableView($this->attributes($data, $user));
            $view->user_id = $user->id;
            $view->slug = (string) Str::ulid();
            $view->save();

            if ($view->is_default) {
                $this->clearOtherDefaults($view);
            }
        });

        return back()->with('success', 'View saved.');
    }

    public function update(TableViewRequest $request, TableView $tableView): RedirectResponse
    {
        $user = $request->user();
        abort_unless($tableView->isVisibleTo($user), 404);
        Gate::authorize('update', $tableView);

        $data = $request->validated();
        $this->assertQueryShape($data);
        $this->assertTeamVisibility($data, $user);

        DB::transaction(function () use ($data, $tableView, $user) {
            $tableView->fill($this->attributes($data, $user));
            $tableView->save();

            if ($tableView->is_default) {
                $this->clearOtherDefaults($tableView);
            }
        });

        return back()->with('success', 'View updated.');
    }

    public function destroy(Request $request, TableView $tableView): RedirectResponse
    {
        $user = $request->user();
        abort_unless($tableView->isVisibleTo($user), 404);
        Gate::authorize('delete', $tableView);

        $tableView->delete();

        return back()->with('success', 'View deleted.');
    }

    public function makeDefault(Request $request, TableView $tableView): RedirectResponse
    {
        $user = $request->user();
        abort_unless($tableView->isVisibleTo($user), 404);
        Gate::authorize('update', $tableView);

        DB::transaction(function () use ($tableView) {
            $tableView->forceFill(['is_default' => true])->save();
            $this->clearOtherDefaults($tableView);
        });

        return back()->with('success', 'Default view updated.');
    }

    /** @param array<string, mixed> $data */
    private function attributes(array $data, \App\Models\User $user): array
    {
        return [
            'table_key' => $data['table_key'],
            'name' => $data['name'],
            'visibility' => $data['visibility'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'payload' => $data['payload'],
            'team_id' => $data['visibility'] === 'team' ? $user->current_team_id : null,
            'sort' => 0,
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertQueryShape(array $data): void
    {
        foreach ((array) ($data['payload']['query'] ?? []) as $name => $tree) {
            ViewShape::assertQueryTree($tree, 'payload.query.'.$name);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertTeamVisibility(array $data, \App\Models\User $user): void
    {
        if ($data['visibility'] === 'team' && ! $user->current_team_id) {
            throw ValidationException::withMessages([
                'visibility' => __('Join or switch to a team before sharing a view with it.'),
            ]);
        }
    }

    /** `is_default` is exclusive per (user, table_key). */
    private function clearOtherDefaults(TableView $view): void
    {
        TableView::where('user_id', $view->user_id)
            ->where('table_key', $view->table_key)
            ->whereKeyNot($view->getKey())
            ->update(['is_default' => false]);
    }
}
