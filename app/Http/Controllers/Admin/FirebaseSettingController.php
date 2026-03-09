<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Settings\FirebaseSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FirebaseSettingController extends Controller
{
    public function index(FirebaseService $firebase): Response
    {
        $settings = app(FirebaseSettings::class);

        return Inertia::render('Admin/Firebase/Settings', [
            'settings' => [
                'enabled' => $settings->enabled,
                'service_account_path' => $settings->service_account_path,
                'api_key' => $settings->api_key,
                'auth_domain' => $settings->auth_domain,
                'project_id' => $settings->project_id,
                'storage_bucket' => $settings->storage_bucket,
                'messaging_sender_id' => $settings->messaging_sender_id,
                'app_id' => $settings->app_id,
                'vapid_key' => $settings->vapid_key,
            ],
            'hasServiceAccount' => $firebase->isEnabled(),
            'webConfig' => $firebase->getWebConfig(),
        ]);
    }

    public function update(Request $request, FirebaseService $firebase): RedirectResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'api_key' => 'nullable|string|max:255',
            'auth_domain' => 'nullable|string|max:255',
            'project_id' => 'nullable|string|max:255',
            'storage_bucket' => 'nullable|string|max:255',
            'messaging_sender_id' => 'nullable|string|max:255',
            'app_id' => 'nullable|string|max:255',
            'vapid_key' => 'nullable|string|max:500',
            'service_account' => 'nullable|file|mimes:json|max:1024',
        ]);

        $settings = app(FirebaseSettings::class);

        // Handle service account file upload
        if ($request->hasFile('service_account')) {
            $file = $request->file('service_account');
            $directory = storage_path('app/firebase');

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $directory . '/service-account.json';
            $file->move($directory, 'service-account.json');

            if (!$firebase->validateServiceAccount($path)) {
                @unlink($path);
                return back()->with('error', 'Invalid service account JSON. Must contain type, project_id, private_key, and client_email.');
            }

            $settings->service_account_path = 'firebase/service-account.json';
        }

        $settings->enabled = $request->boolean('enabled');
        $settings->api_key = $request->api_key;
        $settings->auth_domain = $request->auth_domain;
        $settings->project_id = $request->project_id;
        $settings->storage_bucket = $request->storage_bucket;
        $settings->messaging_sender_id = $request->messaging_sender_id;
        $settings->app_id = $request->app_id;
        $settings->vapid_key = $request->vapid_key;
        $settings->save();

        Cache::forget('firebase_web_config');

        return back()->with('success', 'Firebase settings updated successfully.');
    }

    public function testPush(Request $request, FirebaseService $firebase): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string|max:512',
        ]);

        if (!$firebase->isEnabled()) {
            return back()->with('error', 'Firebase is not enabled or service account is missing.');
        }

        try {
            $success = $firebase->sendTest($request->token);

            return $success
                ? back()->with('success', 'Test push notification sent successfully!')
                : back()->with('error', 'Failed to send test notification. The token may be invalid.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }
}
