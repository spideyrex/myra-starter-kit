<?php

use App\Http\Controllers\Admin\AppearanceController;
use Illuminate\Support\Facades\Route;

/**
 * MYRA v2.8 [B] — the auth-surface editor.
 *
 * No new permission: brand.view opens it, brand.update writes it — the same
 * posture the brand page already has.
 */
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/appearance', [AppearanceController::class, 'index'])
        ->middleware('permission:brand.view')
        ->name('appearance.index');

    Route::middleware('permission:brand.update')->group(function () {
        Route::put('/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
        Route::post('/appearance/preview', [AppearanceController::class, 'preview'])->name('appearance.preview');
        Route::post('/appearance/background/{surface}', [AppearanceController::class, 'uploadBackground'])
            ->whereIn('surface', ['auth', 'page'])
            ->name('appearance.background.store');
        Route::delete('/appearance/background/{surface}', [AppearanceController::class, 'destroyBackground'])
            ->whereIn('surface', ['auth', 'page'])
            ->name('appearance.background.destroy');
    });
});
