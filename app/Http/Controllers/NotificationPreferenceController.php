<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    protected array $notificationTypes = [
        'system' => 'System Notifications',
        'user_action' => 'User Action Notifications',
        'security_alert' => 'Security Alerts',
    ];

    public function index(Request $request): Response
    {
        $preferences = DB::table('notification_preferences')
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('type');

        $items = collect($this->notificationTypes)->map(function ($label, $type) use ($preferences) {
            $pref = $preferences->get($type);
            return [
                'type' => $type,
                'label' => $label,
                'email' => $pref ? (bool) $pref->email : true,
                'database' => $pref ? (bool) $pref->database : true,
            ];
        })->values()->all();

        return Inertia::render('Notifications/Preferences', [
            'preferences' => $items,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.email' => 'required|boolean',
            'preferences.*.database' => 'required|boolean',
        ]);

        foreach ($request->preferences as $pref) {
            DB::table('notification_preferences')->updateOrInsert(
                ['user_id' => $request->user()->id, 'type' => $pref['type']],
                ['email' => $pref['email'], 'database' => $pref['database']],
            );
        }

        return back()->with('success', 'Notification preferences updated.');
    }
}
