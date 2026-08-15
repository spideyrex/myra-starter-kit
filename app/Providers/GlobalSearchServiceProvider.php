<?php

namespace App\Providers;

use App\Admin\Search\GlobalSearch;
use App\Admin\Search\Sources;
use Illuminate\Support\ServiceProvider;

class GlobalSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GlobalSearch::class);
    }

    public function boot(): void
    {
        // Deferred: sources need the route table, and registration throws
        // MissingSearchScopeException outside production when a source forgets
        // its ownership scope. Resolved on first search.
        $this->app->make(GlobalSearch::class)
            ->bootstrapUsing(fn (GlobalSearch $search) => Sources::register($search));
    }
}
