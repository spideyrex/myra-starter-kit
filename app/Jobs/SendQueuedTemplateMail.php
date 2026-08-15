<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Resolves the mailer from EmailSettings at handle time, not at dispatch time:
 * an on-demand mailer cannot survive queue serialisation, and a worker that
 * inherited a previous job's global mail config would send to the wrong host.
 */
final class SendQueuedTemplateMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    /** @param array<int, array{data:string, name:string, mime?:string}> $attachments */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $attachments = [],
        public readonly ?int $logId = null,
    ) {}

    public function handle(EmailService $mail): void
    {
        $mail->deliver($this->to, $this->subject, $this->body, $this->attachments);

        $this->markLog(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        $this->markLog(['status' => 'failed', 'error' => $e->getMessage()]);
    }

    /** @param array<string,mixed> $attributes */
    private function markLog(array $attributes): void
    {
        if ($this->logId !== null) {
            EmailLog::whereKey($this->logId)->update($attributes);
        }
    }
}
