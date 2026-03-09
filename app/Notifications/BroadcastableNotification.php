<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BroadcastableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $sendPush = false;

    public function __construct(
        protected string $message,
        protected string $type = 'info',
        protected array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->sendPush && app(FirebaseService::class)->isEnabled()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            ...$this->extra,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => $this->message,
            'type' => $this->type,
            ...$this->extra,
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->extra['title'] ?? 'Notification',
            'message' => $this->message,
            'action_url' => $this->extra['action_url'] ?? null,
            'type' => $this->type,
        ];
    }
}
