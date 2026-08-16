<?php

namespace App\Http\Middleware;

use App\Settings\AiSettings;
use App\Settings\FirebaseSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    // >>> MYRA v2.6 [C] START
    /** The only general/appearance keys the public `siteSettings` prop may carry. */
    private const PUBLIC_SITE_KEYS = [
        'site_name', 'site_description', 'login_tagline', 'registration_enabled',
        'logo_path', 'favicon_path', 'logo_url', 'favicon_url',
        'primary_color', 'theme', 'logo_position',
        'sidebar_background', 'sidebar_foreground', 'sidebar_accent',
    ];
    // <<< MYRA v2.6 [C] END

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->only(['id', 'name', 'email', 'phone', 'avatar', 'status', 'email_verified_at', 'created_at', 'updated_at']),
                    [
                        'roles' => $request->user()->getRoleNames(),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    ]
                ) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'newToken' => fn () => $request->session()->get('newToken'),
            ],
            'unreadNotificationsCount' => function () use ($request) {
                try {
                    return $request->user()
                        ? $request->user()->unreadNotifications()->count()
                        : 0;
                } catch (\Throwable) {
                    return 0;
                }
            },
            'recentNotifications' => function () use ($request) {
                try {
                    return $request->user()
                        ? $request->user()->notifications()->latest()->take(5)->get()->map(function ($n) {
                            return [
                                'id' => $n->id,
                                'type' => $n->type,
                                'data' => $n->data,
                                'read_at' => $n->read_at?->toISOString(),
                                'created_at' => $n->created_at->diffForHumans(),
                            ];
                        })
                        : [];
                } catch (\Throwable) {
                    return [];
                }
            },
            'impersonating' => fn () => $request->session()->has('impersonator_id'),
            'impersonatorName' => fn () => $request->session()->has('impersonator_id')
                ? \App\Models\User::find($request->session()->get('impersonator_id'))?->name
                : null,
            'currentTeam' => fn () => $request->user()?->currentTeam,
            'teams' => fn () => $request->user()?->teams()->select('teams.id', 'teams.name', 'teams.slug')->get() ?? [],
            // >>> MYRA v2.4 [B] START
            // Server-contributed navigation. A closure, so Inertia skips it on
            // partial reloads; with nothing registered it serialises as [].
            'myraNav' => fn () => \App\Admin\Navigation\NavRegistry::forUser($request->user()),
            // <<< MYRA v2.4 [B] END
            // >>> MYRA v2.6 [C] START
            'brand' => fn () => app(\App\Brand\BrandManager::class)->toInertiaProp(),
            // <<< MYRA v2.6 [C] END
            /** @deprecated v2.6 — use the `brand` prop. Kept for back-compat. */
            'siteSettings' => fn () => cache()->remember('site_settings_shared', 3600, function () {
                $rows = DB::table('settings')->whereIn('group', ['general', 'appearance'])->get();
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row->name] = json_decode($row->payload, true);
                }

                // Compute public URLs for logo and favicon
                if (!empty($settings['logo_path'])) {
                    $settings['logo_url'] = Storage::disk('public')->url($settings['logo_path']);
                }
                if (!empty($settings['favicon_path'])) {
                    $settings['favicon_url'] = Storage::disk('public')->url($settings['favicon_path']);
                }

                // >>> MYRA v2.6 [C] START
                // Whitelisted: admin_email, site_url and timezone used to ship to
                // every unauthenticated guest on /login and the marketing pages.
                return array_intersect_key($settings, array_flip(self::PUBLIC_SITE_KEYS));
                // <<< MYRA v2.6 [C] END
            }),
            'aiEnabled' => fn () => cache()->remember('ai_enabled', 3600, function () {
                try {
                    return app(AiSettings::class)->enabled;
                } catch (\Throwable) {
                    return false;
                }
            }),
            // >>> MYRA v2.5 [D] START
            // Booleans and a build id only — never a provider key. Every value
            // here is derived from config, so all-flags-default serialises to
            // false/false/false and the client wires nothing up.
            'pwaEnabled' => fn () => config('myra.pwa.enabled') === true,
            'buildId' => fn () => cache()->remember('myra_build_id', 3600, function () {
                try {
                    $manifest = public_path('build/manifest.json');
                    $assets = is_file($manifest) ? (md5_file($manifest) ?: 'dev') : 'dev';

                    // >>> MYRA v2.6 [C] START
                    // Folding the brand hash in is what lets a settings-only change
                    // rotate the service-worker shell cache. Without it a changed
                    // manifest and favicon stay pinned behind __MYRA_BUILD__.
                    return substr(md5($assets.'|'.app(\App\Brand\BrandManager::class)->hash()), 0, 8);
                    // <<< MYRA v2.6 [C] END
                } catch (\Throwable) {
                    return 'dev';
                }
            }),
            'aiFeatures' => fn () => [
                'filter' => config('myra.ai.features.filter') === true,
                'schema' => config('myra.ai.features.schema') === true,
                'summarise' => config('myra.ai.features.summarise') === true,
            ],
            // <<< MYRA v2.5 [D] END
            'firebaseConfig' => fn () => cache()->remember('firebase_web_config', 3600, function () {
                try {
                    $settings = app(FirebaseSettings::class);
                    if (!$settings->enabled) {
                        return null;
                    }

                    return array_filter([
                        'apiKey' => $settings->api_key,
                        'authDomain' => $settings->auth_domain,
                        'projectId' => $settings->project_id,
                        'storageBucket' => $settings->storage_bucket,
                        'messagingSenderId' => $settings->messaging_sender_id,
                        'appId' => $settings->app_id,
                        'vapidKey' => $settings->vapid_key,
                    ]) ?: null;
                } catch (\Throwable) {
                    return null;
                }
            }),
        ];
    }
}
