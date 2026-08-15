<?php

namespace App\Admin\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Server half of the inline-editable table columns (toggle, checkbox, select,
 * text input). The editable field list is a server-side literal and the model
 * policy is re-checked here, on top of the route middleware.
 */
trait HandlesInlineUpdates
{
    /** @return array<string,string> e.g. ['is_featured' => 'boolean', 'sort' => 'integer'] */
    abstract protected function inlineEditableFields(): array;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    abstract protected function modelClass(): string;

    public function inlineUpdate(Request $request, int $id): RedirectResponse
    {
        $fields = $this->inlineEditableFields();

        $data = $request->validate([
            'field' => ['required', 'string', Rule::in(array_keys($fields))],
            'value' => ['present'],
        ]);

        $model = $this->modelClass()::findOrFail($id);
        // Gate:: rather than $this->authorize() — the base Controller does not
        // compose AuthorizesRequests, so the latter fatals on adoption.
        Gate::authorize('update', $model);

        $cast = $fields[$data['field']];

        $request->validate(['value' => [$cast === 'boolean' ? 'boolean' : $cast]]);

        $model->update([$data['field'] => match ($cast) {
            'boolean' => $request->boolean('value'),
            'integer' => (int) $data['value'],
            default => $data['value'],
        }]);

        return back()->with('success', __('Updated.'));
    }
}
