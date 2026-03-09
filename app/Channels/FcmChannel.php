<?php

namespace App\Channels;

use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(
        protected FirebaseService $firebase,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!$this->firebase->isEnabled()) {
            return;
        }

        $data = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : $notification->toArray($notifiable);

        $this->firebase->sendToUser(
            $notifiable,
            $data['title'] ?? '',
            $data['message'] ?? $data['body'] ?? '',
            $data['action_url'] ?? null,
            ['type' => $data['type'] ?? 'general'],
        );
    }
}
