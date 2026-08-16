<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'shield' => \App\Http\Middleware\ShieldPermission::class,
            'registration' => \App\Http\Middleware\EnsureRegistrationEnabled::class,
            'active' => \App\Http\Middleware\EnsureActiveUser::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorChallenged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            $status = $response->getStatusCode();
            $errorPages = [403, 404, 419, 500, 503];

            if (in_array($status, $errorPages) && !request()->is('errors/*')) {
                try {
                    // >>> MYRA v2.6 [C] START — MaintenanceSettings::$message is
                    // configurable today and rendered nowhere. 503 now shows it.
                    $props = $status === 503
                        ? ['maintenanceMessage' => app(\App\Brand\BrandManager::class)->maintenanceMessage()]
                        : [];

                    return Inertia::render("Errors/{$status}", $props)
                        ->toResponse(request())
                        ->setStatusCode($status);
                    // <<< MYRA v2.6 [C] END
                } catch (\Throwable) {
                    return $response;
                }
            }

            return $response;
        });
    })->create();
