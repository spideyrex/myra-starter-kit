<?php

use App\Http\Controllers\Admin\AuthLayoutDemoController;
use Illuminate\Support\Facades\Route;

/**
 * MYRA v2.8 [D] — the guest-shell gallery.
 *
 * Replicates the demo group's middleware rather than editing routes/web.php's
 * demo block, so the shared file carries a single `require`.
 */
Route::middleware(['auth', 'verified', 'active', '2fa', 'permission:demo.view'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/demo/auth-layouts', AuthLayoutDemoController::class)->name('demo.auth-layouts');
    });
