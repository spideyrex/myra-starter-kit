<?php

// >>> MYRA v2.6 [A] START
use App\Http\Controllers\Admin\BlockController;
use App\Support\Myra;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix(Myra::adminPrefix())->name('admin.')->group(function () {
    Route::middleware('permission:blocks.view')->group(function () {
        Route::get('/blocks', [BlockController::class, 'index'])->name('blocks.index');
        Route::get('/blocks/{block}', [BlockController::class, 'show'])->name('blocks.show');
        Route::get('/blocks/{block}/preview', [BlockController::class, 'preview'])->name('blocks.preview');
        Route::get('/blocks/{block}/source', [BlockController::class, 'source'])->name('blocks.source');
    })->where('block', '[A-Za-z0-9-]+');
});
// <<< MYRA v2.6 [A] END
