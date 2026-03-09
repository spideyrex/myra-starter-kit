<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    public bool $sendPush = false;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $actionUrl = '',
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->sendPush && app(FirebaseService::class)->isEnabled()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => 'system',
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => 'system',
        ];
    }
}
