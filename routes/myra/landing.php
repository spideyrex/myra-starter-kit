<?php

use App\Http\Controllers\Admin\ComponentDemoController;
use App\Http\Controllers\Admin\LandingController;
use App\Support\Myra;
use Illuminate\Support\Facades\Route;

/**
 * MYRA v2.6 [D] — landing-page template chooser + the v2.6 component demos.
 *
 * The demo routes replicate the demo group's middleware rather than editing
 * routes/web.php's demo block, so the shared file carries a single `require`.
 */
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix(Myra::adminPrefix())->name('admin.')->group(function () {
    Route::middleware('permission:settings.edit')->group(function () {
        Route::get('/landing', [LandingController::class, 'index'])->name('landing.index');
        Route::put('/landing', [LandingController::class, 'update'])->name('landing.update');
    });

    Route::middleware('permission:demo.view')->group(function () {
        Route::get('/demo/empty-and-item', [ComponentDemoController::class, 'emptyAndItem'])->name('demo.empty-and-item');
        Route::get('/demo/chart-primitives', [ComponentDemoController::class, 'chartPrimitives'])->name('demo.chart-primitives');
        Route::get('/demo/otp-and-combobox', [ComponentDemoController::class, 'otpAndCombobox'])->name('demo.otp-and-combobox');
        Route::get('/demo/conversation', [ComponentDemoController::class, 'conversation'])->name('demo.conversation');
        Route::get('/demo/questionnaire', [ComponentDemoController::class, 'questionnaire'])->name('demo.questionnaire');
        Route::get('/demo/map-markers', [ComponentDemoController::class, 'mapMarkers'])->name('demo.map-markers');
        Route::get('/demo/landing-templates', [ComponentDemoController::class, 'landingTemplates'])->name('demo.landing-templates');
    });
});
