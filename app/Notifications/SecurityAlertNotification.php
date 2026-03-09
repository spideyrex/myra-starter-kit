<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    public bool $sendPush = false;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $actionUrl = '',
        protected ?string $ipAddress = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($this->sendPush && app(FirebaseService::class)->isEnabled()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        if ($this->ipAddress) {
            $mail->line('IP Address: ' . $this->ipAddress);
        }

        if ($this->actionUrl) {
            $mail->action('View Details', $this->actionUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'ip_address' => $this->ipAddress,
            'type' => 'security_alert',
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => 'security_alert',
        ];
    }
}
