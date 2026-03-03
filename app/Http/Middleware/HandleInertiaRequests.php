<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

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

                return $settings;
            }),
        ];
    }
}
