<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use App\Health\EnvCheck;
use App\Services\FirebaseService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebaseService::class);

        // >>> MYRA v2.3 [D] START
        $this->app->bind(
            \App\Admin\Report\Contracts\DrillResolver::class,
            \App\Admin\Report\DrillThrough::class,
        );
        $this->app->bind(
            \App\Admin\Report\Contracts\ReportDocumentRenderer::class,
            \App\Admin\Report\Pdf\PdfReportWriter::class,
        );

        if (class_exists(\App\Admin\Export\WriterRegistry::class)) {
            \App\Admin\Export\WriterRegistry::register(
                'pdf',
                static fn (string $key) => new \App\Admin\Export\PdfRowWriter($key),
                'application/pdf',
                static fn () => \App\Admin\Report\Pdf\PdfDocument::isSupported(),
            );
        }
        // <<< MYRA v2.3 [D] END
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::policy(\App\Models\TableView::class, \App\Policies\TableViewPolicy::class);
        Gate::policy(\App\Models\ReportSchedule::class, \App\Policies\ReportSchedulePolicy::class);

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);

        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            UsedDiskSpaceCheck::new()->warnWhenUsedSpaceIsAbovePercentage(70)->failWhenUsedSpaceIsAbovePercentage(90),
            EnvCheck::new(),
        ]);

        // >>> MYRA v2.5 [C] START
        // Fail-soft: a bad gallery declaration must never take down a route or
        // an artisan command. With seeding skipped the Index page falls back to
        // its own list, so the worst case is v2.4.0 behaviour.
        try {
            \App\Admin\Demo\DemoRegistry::seed();
        } catch (\Throwable $e) {
            report($e);
        }
        // <<< MYRA v2.5 [C] END
    }
}
