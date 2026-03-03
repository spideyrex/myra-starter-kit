<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailSettingController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Profile Security
    Route::get('/profile/security', function () {
        $sessions = app(SessionController::class)->index(request());
        return inertia('Profile/Security', array_merge($sessions, [
            'twoFactorEnabled' => request()->user()->hasTwoFactorEnabled(),
            'qrCode' => request()->user()->two_factor_secret ? app(TwoFactorController::class)->qrCode(request())->getData(true) : null,
        ]));
    })->name('profile.security');

    // Two-Factor
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');

    // Sessions
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'index'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

    // Impersonate stop
    Route::post('/admin/stop-impersonate', [UserController::class, 'stopImpersonate'])->name('admin.stop-impersonate');
});

// Admin Routes
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::post('/users/bulk-action', [UserController::class, 'bulkAction'])->middleware('permission:users.edit')->name('users.bulk-action');
    Route::get('/users/export-csv', [UserController::class, 'exportCsv'])->middleware('permission:users.view')->name('users.export-csv');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:users.edit')->name('users.impersonate');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/settings/{group}', [SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');
    Route::post('/settings/appearance', [SettingController::class, 'updateAppearance'])->middleware('permission:settings.edit')->name('settings.update-appearance');

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->middleware('permission:email.view')->name('email-templates.index');
    Route::get('/email-templates/create', [EmailTemplateController::class, 'create'])->middleware('permission:email.create')->name('email-templates.create');
    Route::post('/email-templates', [EmailTemplateController::class, 'store'])->middleware('permission:email.create')->name('email-templates.store');
    Route::get('/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->middleware('permission:email.edit')->name('email-templates.edit');
    Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email.edit')->name('email-templates.update');
    Route::delete('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:email.delete')->name('email-templates.destroy');

    // Email Logs
    Route::get('/email-logs', [EmailLogController::class, 'index'])->middleware('permission:email.view')->name('email-logs.index');

    // Email Settings
    Route::get('/email-settings', [EmailSettingController::class, 'index'])->middleware('permission:settings.view')->name('email-settings.index');
    Route::put('/email-settings', [EmailSettingController::class, 'update'])->middleware('permission:settings.edit')->name('email-settings.update');
    Route::post('/email-settings/test', [EmailSettingController::class, 'testEmail'])->middleware('permission:settings.edit')->name('email-settings.test');

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:activity-log.view')->name('activity-logs.index');

    // Media
    Route::get('/media', [MediaController::class, 'index'])->middleware('permission:media.view')->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->middleware('permission:media.create')->name('media.store');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->middleware('permission:media.delete')->name('media.destroy');

    // System Health
    Route::get('/system-health', [SystemHealthController::class, 'index'])->middleware('permission:system-health.view')->name('system-health.index');

    // Backups
    Route::get('/backups', [BackupController::class, 'index'])->middleware('permission:backups.view')->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->middleware('permission:backups.create')->name('backups.store');
    Route::get('/backups/download/{path}', [BackupController::class, 'download'])->middleware('permission:backups.view')->name('backups.download')->where('path', '.*');
    Route::delete('/backups/{path}', [BackupController::class, 'destroy'])->middleware('permission:backups.delete')->name('backups.destroy')->where('path', '.*');

    // API Tokens
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-tokens.view')->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:api-tokens.create')->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-tokens.delete')->name('api-tokens.destroy');

    // Notifications
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
    Route::get('/notifications/create', [AdminNotificationController::class, 'create'])->middleware('permission:notifications.create')->name('notifications.create');
    Route::post('/notifications', [AdminNotificationController::class, 'store'])->middleware('permission:notifications.create')->name('notifications.store');
});

require __DIR__.'/auth.php';
