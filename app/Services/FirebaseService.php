<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use App\Settings\FirebaseSettings;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;

class FirebaseService
{
    public function isEnabled(): bool
    {
        $settings = app(FirebaseSettings::class);

        return $settings->enabled && $this->hasServiceAccount();
    }

    public function getMessaging(): Messaging
    {
        $path = $this->getServiceAccountPath();

        return (new Factory)
            ->withServiceAccount($path)
            ->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, ?string $actionUrl = null, array $data = []): int
    {
        $tokens = $user->routeNotificationForFcm();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $actionUrl, $data);
    }

    public function sendToTokens(array $tokens, string $title, string $body, ?string $actionUrl = null, array $data = []): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $messaging = $this->getMessaging();

        $notification = Notification::create($title, $body);

        $webPushData = [
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/favicon.ico',
            ],
        ];

        if ($actionUrl) {
            $webPushData['fcm_options'] = ['link' => $actionUrl];
            $data['action_url'] = $actionUrl;
        }

        $webPushConfig = WebPushConfig::fromArray($webPushData);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withWebPushConfig($webPushConfig)
            ->withData(array_map('strval', $data));

        $report = $messaging->sendMulticast($message, $tokens);

        // Clean up invalid tokens
        if ($report->hasFailures()) {
            $invalidTokens = [];
            foreach ($report->failures()->getItems() as $failure) {
                $token = $failure->target()->value();
                $invalidTokens[] = $token;
            }

            if (!empty($invalidTokens)) {
                FcmToken::whereIn('token', $invalidTokens)->delete();
            }
        }

        return $report->successes()->count();
    }

    public function sendTest(string $token): bool
    {
        $messaging = $this->getMessaging();

        $notification = Notification::create(
            'Test Push Notification',
            'If you see this, push notifications are working!'
        );

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withWebPushConfig(WebPushConfig::fromArray([
                'notification' => [
                    'title' => 'Test Push Notification',
                    'body' => 'If you see this, push notifications are working!',
                    'icon' => '/favicon.ico',
                ],
            ]));

        try {
            $messaging->send($message);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function validateServiceAccount(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $contents = json_decode(file_get_contents($path), true);

        if (!is_array($contents)) {
            return false;
        }

        $requiredKeys = ['type', 'project_id', 'private_key', 'client_email'];

        foreach ($requiredKeys as $key) {
            if (!isset($contents[$key])) {
                return false;
            }
        }

        return true;
    }

    public function getWebConfig(): ?array
    {
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
        ]);
    }

    private function hasServiceAccount(): bool
    {
        $path = $this->getServiceAccountPath();

        return $path && file_exists($path);
    }

    private function getServiceAccountPath(): ?string
    {
        $settings = app(FirebaseSettings::class);

        if ($settings->service_account_path) {
            return storage_path('app/' . $settings->service_account_path);
        }

        $envPath = config('firebase.credentials.file');

        return $envPath && file_exists($envPath) ? $envPath : null;
    }
}
