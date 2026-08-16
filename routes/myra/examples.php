<?php

use App\Http\Controllers\Admin\ExampleController;
use Illuminate\Support\Facades\Route;

// >>> MYRA v2.6 [B] START
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:examples.view')->group(function () {
        Route::get('/examples', [ExampleController::class, 'index'])->name('examples.index');
        Route::get('/examples/{example}', [ExampleController::class, 'show'])->name('examples.show');
        Route::get('/examples/{example}/preview', [ExampleController::class, 'preview'])->name('examples.preview');
        Route::get('/examples/{example}/source', [ExampleController::class, 'source'])->name('examples.source');
    })->where('example', '[a-z0-9-]+');
});
// <<< MYRA v2.6 [B] END
