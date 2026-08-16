<?php

namespace App\Admin\Traits;

use App\Admin\Resource\SingularResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

trait HandlesSingularRecord
{
    abstract protected function singular(): SingularResource;

    public function show(): Response
    {
        $singular = $this->singular();

        Gate::authorize($singular->viewAbility());

        $record = $singular->resolve();

        // The rule keys are the whitelist: nothing the form cannot submit is
        // ever handed back to the client.
        $values = [];
        foreach (array_keys($singular->validationRules()) as $field) {
            $values[$field] = $record?->{$field};
        }

        return Inertia::render($singular->pageComponent(), [
            'singularKey' => $singular->key(),
            'record' => $values,
            'exists' => $record !== null,
            'canEdit' => Gate::allows($singular->editAbility()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $singular = $this->singular();

        Gate::authorize($singular->editAbility());

        $validated = $request->validate($singular->validationRules());

        $record = $singular->resolve();

        if ($record === null) {
            $class = $singular->modelClass();
            $record = new $class;

            foreach ($singular->creationAttributeValues() as $attribute => $value) {
                $record->{$attribute} = $value;
            }
        }

        $record->fill($validated);
        $record->save();

        if ($record->wasRecentlyCreated) {
            foreach ($singular->relationshipNames() as $name) {
                if ($request->has($name)) {
                    $record->{$name}()->sync((array) $request->input($name));
                }
            }
        }

        if ($callback = $singular->afterSaveCallback()) {
            $callback($record, $request);
        }

        return back()->with('success', __('Saved successfully.'));
    }
}
