<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\BrandManifestController;
use Illuminate\Support\Facades\Route;

// Public. The manifest route registers regardless of myra.pwa.enabled; without
// the <link rel="manifest"> nothing requests it, so it is harmless either way.
//
// It lives under /brand/ because public/manifest.webmanifest is a committed
// static file: Apache's `RewriteCond %{REQUEST_FILENAME} !-f`, nginx's
// `try_files $uri $uri/ /index.php`, Herd and `php artisan serve` all serve the
// file and never reach PHP, so a route on the bare URL is dead in every real
// deployment. Nothing exists at public/brand/, so this URL cannot be shadowed.
Route::get('/brand/manifest.webmanifest', BrandManifestController::class)->name('brand.manifest');

// The legacy URL, kept so a browser that installed the app before 2.6 (and the
// service-worker shell precache) still resolves. Shadowed by the static file in
// production; brand:publish keeps that file branded.
Route::get('/manifest.webmanifest', BrandManifestController::class)->name('brand.manifest.legacy');
Route::get('/brand/icon-{size}.png', [BrandAssetController::class, 'icon'])
    ->whereIn('size', ['192', '512'])
    ->name('brand.icon');
Route::get('/brand/favicon.svg', [BrandAssetController::class, 'favicon'])->name('brand.favicon');

Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/brand', [BrandController::class, 'index'])->middleware('permission:brand.view')->name('brand.index');
    Route::put('/brand', [BrandController::class, 'update'])->middleware('permission:brand.update')->name('brand.update');
    Route::post('/brand/asset/{slot}', [BrandController::class, 'uploadAsset'])->middleware('permission:brand.update')->name('brand.asset.store');
    Route::delete('/brand/asset/{slot}', [BrandController::class, 'destroyAsset'])->middleware('permission:brand.update')->name('brand.asset.destroy');
    Route::post('/brand/preview', [BrandController::class, 'preview'])->middleware('permission:brand.update')->name('brand.preview');
    Route::post('/brand/reset', [BrandController::class, 'reset'])->middleware('permission:brand.update')->name('brand.reset');
});
