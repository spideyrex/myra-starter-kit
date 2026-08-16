<?php

use App\Http\Controllers\Admin\LandingPreviewController;
use Illuminate\Support\Facades\Route;

/**
 * MYRA v2.7 [C] — the page-builder preview slot writer.
 *
 * Mirrors routes/myra/landing.php's middleware stack rather than editing the
 * shared admin group, so routes/web.php carries a single `require`.
 */
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/landing/builder/preview', [LandingPreviewController::class, 'store'])
        ->middleware(['permission:settings.edit', 'throttle:120,1'])
        ->name('landing.builder.preview');
});
