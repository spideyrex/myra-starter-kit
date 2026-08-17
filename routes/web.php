<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DemoController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailSettingController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\FirebaseSettingController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\InlineUploadController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportScheduleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TableViewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TeamController;
use App\Support\Myra;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('home');

// >>> MYRA v2.6 [D] START
require __DIR__.'/myra/landing.php';
// <<< MYRA v2.6 [D] END

// Public pages & blog
Route::get('/pages/{slug}', [PublicPageController::class, 'show'])->name('pages.show');
Route::get('/blog', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/blog/{slug}', [PublicArticleController::class, 'show'])->name('articles.show');

// >>> MYRA v2.6 [C] START
require __DIR__.'/myra/brand.php';
// <<< MYRA v2.6 [C] END

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'active', '2fa'])
    ->name('dashboard');

Route::middleware(['auth', 'active', '2fa'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.destroy');

    // Profile Security
    Route::get('/profile/security', function () {
        $sessions = app(SessionController::class)->index(request());

        return inertia('Profile/Security', array_merge($sessions, [
            'twoFactorEnabled' => request()->user()->hasTwoFactorEnabled(),
            'qrCode' => request()->user()->two_factor_secret && ! request()->user()->two_factor_confirmed_at
                ? app(TwoFactorController::class)->qrCode(request())->getData(true)
                : null,
        ]));
    })->name('profile.security');

    // Two-Factor
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('two-factor.confirm');
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:6,1')->name('two-factor.verify');

    // Sessions
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'index'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

    // Impersonate stop
    Route::post(Myra::adminPath('stop-impersonate'), [UserController::class, 'stopImpersonate'])->name('admin.stop-impersonate');

    // FCM Tokens
    Route::post('/fcm-tokens', [FcmTokenController::class, 'store'])->name('fcm-tokens.store');
    Route::delete('/fcm-tokens', [FcmTokenController::class, 'destroy'])->name('fcm-tokens.destroy');

    // AI Assist (available to any authenticated user)
    Route::post('/ai/assist', [AiController::class, 'assist'])->middleware('throttle:30,1')->name('ai.assist');

    // Team switching
    Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'active', '2fa'])->prefix(Myra::adminPrefix())->name('admin.')->group(function () {
    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::post('/users/bulk-action', [UserController::class, 'bulkAction'])->middleware('permission:users.edit')->name('users.bulk-action');
    Route::get('/users/export-csv', [UserController::class, 'exportCsv'])->middleware(['permission:users.view', 'throttle:5,1'])->name('users.export-csv');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:users.edit')->name('users.impersonate');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])->middleware('permission:users.edit')->name('users.restore');
    Route::delete('/users/{user}/force-delete', [UserController::class, 'forceDelete'])->middleware('permission:users.delete')->name('users.force-delete');

    // >>> MYRA v2.2 [C] START
    // Import — the per-resource ability is resolved from the registry inside the
    // controller, so a blanket permission: middleware would be the wrong gate.
    Route::post('/import/{resource}/preview', [ImportController::class, 'preview'])->middleware('throttle:10,1')->name('import.preview');
    Route::post('/import/{resource}/validate', [ImportController::class, 'validateRows'])->middleware('throttle:10,1')->name('import.validate');
    Route::post('/import/{resource}/commit', [ImportController::class, 'commit'])->middleware('throttle:10,1')->name('import.commit');
    Route::get('/import/{resource}/failures/{token}', [ImportController::class, 'failures'])->name('import.failures');
    // <<< MYRA v2.2 [C] END

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::post('/roles/{role}/clone', [RoleController::class, 'clone'])->middleware('permission:roles.create')->name('roles.clone');
    Route::post('/roles/{role}/toggle-active', [RoleController::class, 'toggleActive'])->middleware('permission:roles.edit')->name('roles.toggle-active');
    Route::post('/roles/{role}/toggle-visible', [RoleController::class, 'toggleVisible'])->middleware('permission:roles.edit')->name('roles.toggle-visible');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    // >>> MYRA v2.7 [C] START
    Route::post('/roles/reorder', [RoleController::class, 'reorder'])->middleware('permission:roles.edit')->name('roles.reorder');
    // <<< MYRA v2.7 [C] END

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/settings/ai', [AiController::class, 'updateSettings'])->middleware('permission:settings.edit')->name('settings.update-ai');
    Route::put('/settings/{group}', [SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');
    Route::post('/settings/appearance', [SettingController::class, 'updateAppearance'])->middleware('permission:settings.edit')->name('settings.update-appearance');
    Route::post('/settings/homepage', [SettingController::class, 'updateHomepage'])->middleware('permission:settings.edit')->name('settings.update-homepage');
    Route::post('/ai/test', [AiController::class, 'testConnection'])->middleware('permission:settings.edit')->name('ai.test-connection');

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->middleware('permission:email.view')->name('email-templates.index');
    Route::get('/email-templates/create', [EmailTemplateController::class, 'create'])->middleware('permission:email.create')->name('email-templates.create');
    Route::post('/email-templates', [EmailTemplateController::class, 'store'])->middleware('permission:email.create')->name('email-templates.store');
    Route::get('/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->middleware('permission:email.edit')->name('email-templates.edit');
    Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email.edit')->name('email-templates.update');
    Route::delete('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:email.delete')->name('email-templates.destroy');
    Route::post('/email-templates/{emailTemplate}/send-test', [EmailTemplateController::class, 'sendTest'])->middleware('permission:email.edit')->name('email-templates.send-test');

    // Email Logs
    Route::get('/email-logs', [EmailLogController::class, 'index'])->middleware('permission:email.view')->name('email-logs.index');
    Route::get('/email-logs/export-csv', [EmailLogController::class, 'exportCsv'])->middleware('permission:email.view')->name('email-logs.export-csv');
    Route::post('/email-logs/bulk-action', [EmailLogController::class, 'bulkDestroy'])->middleware('permission:email.delete')->name('email-logs.bulk-action');

    // Email Settings
    Route::get('/email-settings', [EmailSettingController::class, 'index'])->middleware('permission:settings.view')->name('email-settings.index');
    Route::put('/email-settings', [EmailSettingController::class, 'update'])->middleware('permission:settings.edit')->name('email-settings.update');
    Route::post('/email-settings/test', [EmailSettingController::class, 'testEmail'])->middleware('permission:settings.edit')->name('email-settings.test');

    // Firebase Settings
    Route::get('/firebase-settings', [FirebaseSettingController::class, 'index'])->middleware('permission:firebase.view')->name('firebase-settings.index');
    Route::put('/firebase-settings', [FirebaseSettingController::class, 'update'])->middleware('permission:firebase.edit')->name('firebase-settings.update');
    Route::post('/firebase-settings/test', [FirebaseSettingController::class, 'testPush'])->middleware('permission:firebase.edit')->name('firebase-settings.test');

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:activity-log.view')->name('activity-logs.index');
    Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->middleware('permission:activity-log.view')->name('activity-logs.export-csv');
    Route::post('/activity-logs/bulk-action', [ActivityLogController::class, 'bulkDestroy'])->middleware('permission:activity-log.view')->name('activity-logs.bulk-action');

    // Media
    Route::get('/media', [MediaController::class, 'index'])->middleware('permission:media.view')->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->middleware('permission:media.create')->name('media.store');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->middleware('permission:media.delete')->name('media.destroy');
    Route::post('/media/bulk-action', [MediaController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('media.bulk-action');

    // >>> MYRA v2.2 [A] START
    // Inline (markdown) image uploads — private disk, ownership encoded in the path.
    Route::post('/uploads/inline', [InlineUploadController::class, 'store'])
        ->middleware(['permission:media.create', 'throttle:30,1'])->name('uploads.inline');
    // No `permission:media.view` middleware: the controller authorises owner OR
    // media.view, and the middleware would lock an owner out of their own image.
    // {path} is the storage path WITHOUT its 'inline/' prefix — the controller
    // re-adds it — so the public URL reads /uploads/inline/{user}/{ulid}.ext
    // rather than doubling the segment.
    Route::get('/uploads/inline/{path}', [InlineUploadController::class, 'show'])
        ->where('path', '[0-9]+/[0-9A-HJKMNP-TV-Z]{26}\.[a-z]{3,4}')->name('uploads.inline.show');
    // <<< MYRA v2.2 [A] END

    // System Health
    Route::get('/system-health', [SystemHealthController::class, 'index'])->middleware('permission:system-health.view')->name('system-health.index');

    // Backups
    Route::get('/backups', [BackupController::class, 'index'])->middleware('permission:backups.view')->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->middleware(['permission:backups.create', 'throttle:3,1'])->name('backups.store');
    Route::get('/backups/download/{path}', [BackupController::class, 'download'])->middleware('permission:backups.view')->name('backups.download')->where('path', '.*');
    Route::delete('/backups/{path}', [BackupController::class, 'destroy'])->middleware('permission:backups.delete')->name('backups.destroy')->where('path', '.*');

    // API Tokens
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-tokens.view')->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware(['permission:api-tokens.create', 'throttle:10,1'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-tokens.delete')->name('api-tokens.destroy');

    // Notifications
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
    Route::get('/notifications/create', [AdminNotificationController::class, 'create'])->middleware('permission:notifications.create')->name('notifications.create');
    Route::post('/notifications', [AdminNotificationController::class, 'store'])->middleware('permission:notifications.create')->name('notifications.store');
    Route::post('/notifications/bulk-action', [AdminNotificationController::class, 'bulkAction'])->middleware('permission:notifications.view')->name('notifications.bulk-action');

    // Pages
    Route::get('/pages', [PageController::class, 'index'])->middleware('permission:pages.view')->name('pages.index');
    Route::get('/pages/create', [PageController::class, 'create'])->middleware('permission:pages.create')->name('pages.create');
    Route::post('/pages', [PageController::class, 'store'])->middleware('permission:pages.create')->name('pages.store');
    Route::get('/pages/{page}/edit', [PageController::class, 'edit'])->middleware('permission:pages.edit')->name('pages.edit');
    Route::put('/pages/{page}', [PageController::class, 'update'])->middleware('permission:pages.edit')->name('pages.update');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->middleware('permission:pages.delete')->name('pages.destroy');
    Route::post('/pages/bulk-action', [PageController::class, 'bulkAction'])->middleware('permission:pages.edit')->name('pages.bulk-action');
    Route::post('/pages/{page}/restore', [PageController::class, 'restore'])->middleware('permission:pages.edit')->name('pages.restore');
    Route::delete('/pages/{page}/force-delete', [PageController::class, 'forceDelete'])->middleware('permission:pages.delete')->name('pages.force-delete');
    Route::post('/pages/{page}/replicate', [PageController::class, 'replicate'])->middleware('permission:pages.create')->name('pages.replicate');

    // Articles
    Route::get('/articles', [ArticleController::class, 'index'])->middleware('permission:articles.view')->name('articles.index');
    Route::get('/articles/create', [ArticleController::class, 'create'])->middleware('permission:articles.create')->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware('permission:articles.create')->name('articles.store');
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->middleware('permission:articles.edit')->name('articles.edit');
    Route::put('/articles/{article}', [ArticleController::class, 'update'])->middleware('permission:articles.edit')->name('articles.update');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->middleware('permission:articles.delete')->name('articles.destroy');
    Route::post('/articles/bulk-action', [ArticleController::class, 'bulkAction'])->middleware('permission:articles.edit')->name('articles.bulk-action');
    Route::post('/articles/{article}/restore', [ArticleController::class, 'restore'])->middleware('permission:articles.edit')->name('articles.restore');
    Route::delete('/articles/{article}/force-delete', [ArticleController::class, 'forceDelete'])->middleware('permission:articles.delete')->name('articles.force-delete');
    Route::post('/articles/{article}/replicate', [ArticleController::class, 'replicate'])->middleware('permission:articles.create')->name('articles.replicate');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit')->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('categories.destroy');

    // >>> MYRA v2.2 [B] START
    // Saved table views. No `permission:` middleware — a blanket views
    // permission would grant nothing about the table being viewed, and the
    // payload is opaque data replayed as query params through the existing
    // index route, which keeps its own gate.
    Route::get('/table-views', [TableViewController::class, 'index'])->name('table-views.index');
    Route::post('/table-views', [TableViewController::class, 'store'])->middleware('throttle:30,1')->name('table-views.store');
    Route::put('/table-views/{tableView}', [TableViewController::class, 'update'])->middleware('throttle:30,1')->name('table-views.update');
    Route::delete('/table-views/{tableView}', [TableViewController::class, 'destroy'])->name('table-views.destroy');
    Route::post('/table-views/{tableView}/default', [TableViewController::class, 'makeDefault'])->name('table-views.default');
    // <<< MYRA v2.2 [B] END

    // >>> MYRA v2.5 [A] START
    // Per-user dashboard layouts. The stored blob is a request, never a schema:
    // every instance is re-resolved server-side on read AND rejected on write.
    Route::get('/dashboard-catalogue', [\App\Http\Controllers\Admin\DashboardLayoutController::class, 'catalogue'])
        ->middleware('permission:dashboard.customise')->name('dashboard-catalogue.index');
    Route::put('/dashboard-layouts/{dashboard}', [\App\Http\Controllers\Admin\DashboardLayoutController::class, 'update'])
        ->middleware(['permission:dashboard.customise', 'throttle:30,1'])
        ->name('dashboard-layouts.update');
    Route::delete('/dashboard-layouts/{dashboard}', [\App\Http\Controllers\Admin\DashboardLayoutController::class, 'destroy'])
        ->middleware(['permission:dashboard.customise', 'throttle:30,1'])
        ->name('dashboard-layouts.destroy');
    // <<< MYRA v2.5 [A] END

    // >>> MYRA v2.7 [B] START
    // Per-role default dashboards. Authoring a document rendered for other
    // people is an escalation surface, so it carries its own ability.
    Route::get('/role-dashboards', [\App\Http\Controllers\Admin\RoleDashboardController::class, 'index'])
        ->middleware('permission:dashboard.manage-roles')->name('role-dashboards.index');
    Route::get('/role-dashboards/{role}/edit', [\App\Http\Controllers\Admin\DashboardController::class, 'editForRole'])
        ->middleware('permission:dashboard.manage-roles')->name('role-dashboards.edit');
    Route::put('/role-dashboards/{role}', [\App\Http\Controllers\Admin\RoleDashboardController::class, 'update'])
        ->middleware(['permission:dashboard.manage-roles', 'throttle:30,1'])
        ->name('role-dashboards.update');
    Route::delete('/role-dashboards/{role}', [\App\Http\Controllers\Admin\RoleDashboardController::class, 'destroy'])
        ->middleware(['permission:dashboard.manage-roles', 'throttle:30,1'])
        ->name('role-dashboards.destroy');
    // <<< MYRA v2.7 [B] END

    // Global Search
    // >>> MYRA v2.2 [D] START
    Route::get('/search', [SearchController::class, 'index'])
        ->middleware(['permission:search.view', 'throttle:60,1'])
        ->name('search');
    // <<< MYRA v2.2 [D] END

    // Demo / Feature Showcase (all gated by demo.view)
    Route::middleware('permission:demo.view')->group(function () {
        Route::get('/demo', [DemoController::class, 'index'])->name('demo.index');
        Route::get('/demo/rich-text-editor', [DemoController::class, 'richTextEditor'])->name('demo.rich-text-editor');
        Route::get('/demo/repeater-field', [DemoController::class, 'repeaterField'])->name('demo.repeater-field');
        Route::get('/demo/form-builder', [DemoController::class, 'formBuilder'])->name('demo.form-builder');
        Route::get('/demo/bulk-actions', [DemoController::class, 'bulkActions'])->name('demo.bulk-actions');
        Route::post('/demo/bulk-action', [DemoController::class, 'bulkAction'])->name('demo.bulk-action');
        Route::get('/demo/soft-deletes', [DemoController::class, 'softDeletes'])->name('demo.soft-deletes');
        Route::post('/demo/soft-deletes/{id}/restore', [DemoController::class, 'demoRestore'])->name('demo.restore');
        Route::delete('/demo/soft-deletes/{id}/force-delete', [DemoController::class, 'demoForceDelete'])->name('demo.force-delete');
        Route::delete('/demo/soft-deletes/{id}', [DemoController::class, 'demoSoftDelete'])->name('demo.soft-delete');
        Route::get('/demo/action-modals', [DemoController::class, 'actionModals'])->name('demo.action-modals');
        Route::put('/demo/action-modals/{id}', [DemoController::class, 'demoUpdateTask'])->name('demo.update-task');
        Route::delete('/demo/action-modals/{id}', [DemoController::class, 'demoDeleteTask'])->name('demo.delete-task');
        Route::post('/demo/action-modals/{id}/replicate', [DemoController::class, 'demoReplicateTask'])->name('demo.replicate-task');
        Route::post('/demo/action-modals/{id}/archive', [DemoController::class, 'demoArchiveTask'])->name('demo.archive-task');
        // >>> MYRA v2.2 [C] START
        Route::get('/demo/import-export', [DemoController::class, 'importExport'])->name('demo.import-export');
        Route::get('/demo/export-csv', [DemoController::class, 'exportCsv'])->name('demo.export-csv');
        Route::get('/demo/import-sample', [DemoController::class, 'importSample'])->name('demo.import-sample');
        // <<< MYRA v2.2 [C] END
        Route::get('/demo/global-search', [DemoController::class, 'globalSearch'])->name('demo.global-search');
        // >>> MYRA v2.4 [C] START
        Route::get('/demo/tenancy', [DemoController::class, 'tenancy'])->name('demo.tenancy');
        // <<< MYRA v2.4 [C] END
        // >>> MYRA v2.5 [C] START
        // ReportDelivery.vue has shipped since v2.3 with no route at all — the
        // gallery registry makes that drift a failing test, so it gets one here.
        Route::get('/demo/playground', [DemoController::class, 'playground'])->name('demo.playground');
        Route::get('/demo/report-delivery', [DemoController::class, 'reportDelivery'])->name('demo.report-delivery');
        // <<< MYRA v2.5 [C] END
        // >>> MYRA v2.2 [B] START
        Route::get('/demo/saved-views', [DemoController::class, 'savedViews'])->name('demo.saved-views');
        // <<< MYRA v2.2 [B] END
        // >>> MYRA v2.3 [B] START
        Route::get('/demo/reports', [DemoController::class, 'reports'])->name('demo.reports');
        // <<< MYRA v2.3 [B] END
        // >>> MYRA v2.5 [B] START
        Route::get('/demo/live-widgets', [DemoController::class, 'liveWidgets'])->name('demo.live-widgets');
        // <<< MYRA v2.5 [B] END

        // Advanced Feature Demos
        Route::get('/demo/inline-editing', [DemoController::class, 'inlineEditing'])->name('demo.inline-editing');
        Route::match(['put', 'patch'], '/demo/inline-update/{id}', [DemoController::class, 'demoInlineUpdate'])->name('demo.inline-update');
        Route::get('/demo/conditional-fields', [DemoController::class, 'conditionalFields'])->name('demo.conditional-fields');
        Route::get('/demo/infolist', [DemoController::class, 'infolist'])->name('demo.infolist');
        Route::get('/demo/relation-manager', [DemoController::class, 'relationManager'])->name('demo.relation-manager');
        // >>> MYRA v2.4 [B] START
        // Clusters demo: a nested resource (courses → lessons) and a singular
        // resource (site identity). scopeBindings() turns a child that belongs to a
        // different parent into a 404 with no controller code at all.
        Route::get('/learning/courses', [\App\Http\Controllers\Admin\MyraCourseController::class, 'index'])->name('learning.courses.index');
        Route::get('/learning/courses/{course}/lessons', [\App\Http\Controllers\Admin\MyraLessonController::class, 'index'])
            ->scopeBindings()->name('learning.courses.lessons.index');
        Route::post('/learning/courses/{course}/lessons', [\App\Http\Controllers\Admin\MyraLessonController::class, 'store'])
            ->scopeBindings()->name('learning.courses.lessons.store');
        Route::delete('/learning/courses/{course}/lessons/{lesson}', [\App\Http\Controllers\Admin\MyraLessonController::class, 'destroy'])
            ->scopeBindings()->name('learning.courses.lessons.destroy');
        Route::get('/learning/site-identity', [\App\Http\Controllers\Admin\MyraSiteIdentityController::class, 'show'])->name('learning.site-identity.show');
        Route::put('/learning/site-identity', [\App\Http\Controllers\Admin\MyraSiteIdentityController::class, 'update'])->name('learning.site-identity.update');
        // <<< MYRA v2.4 [B] END
        Route::post('/demo/relation-create', [DemoController::class, 'demoRelationCreate'])->name('demo.relation-create');
        Route::get('/demo/grouping', [DemoController::class, 'grouping'])->name('demo.grouping');
        // >>> MYRA v2.4 [D] START
        Route::get('/demo/scale', [DemoController::class, 'scale'])->name('demo.scale');
        Route::get('/demo/scale-cursor', [DemoController::class, 'scaleCursor'])->name('demo.scale-cursor');
        // <<< MYRA v2.4 [D] END
        // >>> MYRA v2.5 [D] START
        Route::get('/demo/ai-filter', [DemoController::class, 'aiFilter'])->name('demo.ai-filter');
        Route::get('/demo/offline-shell', [DemoController::class, 'offlineShell'])->name('demo.offline-shell');
        // <<< MYRA v2.5 [D] END
        Route::get('/demo/reordering', [DemoController::class, 'reordering'])->name('demo.reordering');
        Route::post('/demo/reorder', [DemoController::class, 'demoReorder'])->name('demo.reorder');
        Route::get('/demo/widgets', [DemoController::class, 'widgets'])->name('demo.widgets');
        Route::get('/demo/field-types', [DemoController::class, 'fieldTypes'])->name('demo.field-types');
        // >>> MYRA v2.4 [A] START
        Route::get('/demo/plugins', [DemoController::class, 'plugins'])->name('demo.plugins');
        // <<< MYRA v2.4 [A] END
        // >>> MYRA v2.5 [A] START
        Route::get('/demo/dashboard-editor', [DemoController::class, 'dashboardEditor'])->name('demo.dashboard-editor');
        // <<< MYRA v2.5 [A] END
        Route::get('/demo/code-editor', [DemoController::class, 'codeEditor'])->name('demo.code-editor');
        Route::get('/demo/advanced-filters', [DemoController::class, 'advancedFilters'])->name('demo.advanced-filters');
        Route::get('/demo/wizard', [DemoController::class, 'wizardDemo'])->name('demo.wizard');
        Route::get('/demo/map', [DemoController::class, 'map'])->name('demo.map');
        // >>> MYRA v2.7 [D] START
        Route::get('/demo/role-dashboards', [DemoController::class, 'roleDashboards'])->name('demo.role-dashboards');
        // <<< MYRA v2.7 [D] END
    }); // end demo group

    // >>> MYRA v2.3 [B] START
    // Reports. `data` and `widgets` are POST because a rule tree does not fit
    // in a URL; both are still Gate-checked per report inside the controller.
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])
        ->middleware('permission:reports.view')->name('reports.show');
    Route::post('/reports/{report}/data', [ReportController::class, 'data'])
        ->middleware(['permission:reports.view', 'throttle:60,1'])->name('reports.data');
    Route::post('/dashboard/widgets/data', [ReportController::class, 'widgets'])
        ->middleware(['permission:reports.view', 'throttle:60,1'])->name('reports.widgets');
    Route::get('/reports/{report}/export', [ReportController::class, 'export'])
        ->middleware(['permission:reports.export', 'throttle:10,1'])->name('reports.export');
    // <<< MYRA v2.3 [B] END

    // The report-schedules routes ship with the report-delivery bundle, which
    // owns ReportScheduleController. Registering them here binds a class that
    // does not exist on this branch.
    // >>> MYRA v2.3 [D] START
    // Scheduled report delivery. Per-schedule authority is the ReportSchedulePolicy;
    // the middleware only keeps the whole surface behind the schedule ability.
    Route::get('/report-schedules', [ReportScheduleController::class, 'index'])
        ->middleware('permission:reports.schedule')->name('report-schedules.index');
    Route::post('/report-schedules', [ReportScheduleController::class, 'store'])
        ->middleware(['permission:reports.schedule', 'throttle:20,1'])->name('report-schedules.store');
    Route::put('/report-schedules/{reportSchedule}', [ReportScheduleController::class, 'update'])
        ->middleware(['permission:reports.schedule', 'throttle:20,1'])->name('report-schedules.update');
    Route::delete('/report-schedules/{reportSchedule}', [ReportScheduleController::class, 'destroy'])
        ->middleware('permission:reports.schedule')->name('report-schedules.destroy');
    Route::post('/report-schedules/{reportSchedule}/test', [ReportScheduleController::class, 'test'])
        ->middleware(['permission:reports.schedule', 'throttle:3,10'])->name('report-schedules.test');
    // <<< MYRA v2.3 [D] END

    // >>> MYRA v2.5 [D] START
    // Tighter throttles than ai.assist's 30/min because each call is a paid
    // provider round-trip. All three 404 while their config flag is false.
    Route::post('/ai/filter', [\App\Http\Controllers\Admin\AiFilterController::class, 'compile'])
        ->middleware(['permission:ai.filter', 'throttle:10,1'])->name('ai.filter');
    Route::post('/ai/schema', [\App\Http\Controllers\Admin\AiFilterController::class, 'schema'])
        ->middleware(['permission:ai.schema', 'throttle:5,1'])->name('ai.schema');
    Route::post('/ai/summarise', [\App\Http\Controllers\Admin\AiFilterController::class, 'summarise'])
        ->middleware(['permission:ai.summarise', 'throttle:10,1'])->name('ai.summarise');
    // <<< MYRA v2.5 [D] END

    // myra:routes — make:myra-* commands insert generated routes above this line. Do not remove.
});

// >>> MYRA v2.6 [A] START
require __DIR__.'/myra/blocks.php';
// <<< MYRA v2.6 [A] END

require __DIR__.'/auth.php';

// >>> MYRA v2.6 [B] START
require __DIR__.'/myra/examples.php';
// <<< MYRA v2.6 [B] END

// >>> MYRA v2.7 [B] START
require __DIR__.'/myra/pagebuilder.php';
// <<< MYRA v2.7 [B] END
// >>> MYRA v2.7 [C] START
require __DIR__.'/myra/pagebuilder-preview.php';
// <<< MYRA v2.7 [C] END
// >>> MYRA v2.7 [D] START
// Replicates the demo group's middleware rather than editing that block, so
// this shared file only ever grows at the end.
Route::middleware(['auth', 'verified', 'active', '2fa', 'permission:demo.view'])
    ->prefix(Myra::adminPrefix())->name('admin.')->group(function () {
        Route::get('/demo/page-builder', [\App\Http\Controllers\Admin\ComponentDemoController::class, 'pageBuilder'])
            ->name('demo.page-builder');
    });
// <<< MYRA v2.7 [D] END

// >>> MYRA v2.8 [B] START
require __DIR__.'/myra/appearance.php';
// <<< MYRA v2.8 [B] END

// >>> MYRA v2.8 [D] START
require __DIR__.'/myra/appearance-demo.php';
// <<< MYRA v2.8 [D] END

// >>> MYRA v2.8 [PREFIX] START
// Old /admin/... links keep working after the prefix moved. GET only: a 302
// turns a POST into a GET, so redirecting writes would silently drop the body.
// Registered last, and only when the legacy prefix is not the live one.
if (config('myra.admin.legacy_redirect')) {
    $legacyPrefix = trim((string) config('myra.admin.legacy_prefix', 'admin'), '/');

    if ($legacyPrefix !== '' && $legacyPrefix !== Myra::adminPrefix()) {
        Route::get($legacyPrefix.'/{path?}', function (\Illuminate\Http\Request $request, string $path = '') {
            $target = Myra::adminPath($path);
            $query = $request->getQueryString();

            return redirect($query === null ? $target : $target.'?'.$query);
        })->where('path', '.*')->name('myra.admin-legacy');
    }
}
// <<< MYRA v2.8 [PREFIX] END
