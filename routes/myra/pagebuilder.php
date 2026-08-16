<?php

use App\Http\Controllers\Admin\PageBuilderController;
use App\Support\Myra;
use Illuminate\Support\Facades\Route;

/**
 * MYRA v2.7 [B] — the landing page builder editor.
 *
 * Middleware mirrors routes/myra/landing.php exactly; /admin/landing keeps
 * working unchanged beside it.
 */
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix(Myra::adminPrefix())->name('admin.')->group(function () {
    Route::middleware('permission:settings.edit')->group(function () {
        Route::get('/landing/builder', [PageBuilderController::class, 'index'])->name('landing.builder.index');
        Route::put('/landing/builder', [PageBuilderController::class, 'update'])->name('landing.builder.update');
        Route::post('/landing/builder/convert', [PageBuilderController::class, 'convert'])->name('landing.builder.convert');
        Route::post('/landing/builder/image', [PageBuilderController::class, 'image'])
            ->middleware('throttle:30,1')
            ->name('landing.builder.image');
    });
});
