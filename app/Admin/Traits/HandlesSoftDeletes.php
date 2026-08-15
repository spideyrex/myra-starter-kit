<?php

namespace App\Admin\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Per-verb authorization for soft-delete workflows.
 *
 * Route middleware gates the *endpoint*; a bulk endpoint accepts several verbs,
 * so each verb must be authorized on its own ability. Without this, a principal
 * holding only `{module}.edit` can post `force_delete` to the edit-gated bulk
 * route and bypass `{module}.delete`.
 */
trait HandlesSoftDeletes
{
    /** Verb → ability. Destructive verbs require `{module}.delete`. */
    protected function authorizeBulkVerb(string $verb, string $module): void
    {
        $ability = match ($verb) {
            'delete', 'force_delete' => "{$module}.delete",
            default => "{$module}.edit",
        };

        $this->authorizeAbility($ability);
    }

    /** In-controller check that runs in addition to the route middleware. */
    protected function authorizeAbility(string $ability): void
    {
        abort_unless(Auth::user()?->can($ability) === true, 403);
    }
}
