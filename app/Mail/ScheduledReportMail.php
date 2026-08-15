<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Attachments travel as bytes, not paths: the rendered document is deleted the
 * moment the mailable is built, so nothing lingers on disk waiting for a worker.
 */
final class ScheduledReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param array<int, array{data:string, name:string, mime?:string}> $files */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $html,
        public readonly array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->html);
    }

    /** @return array<int, \Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $file) => \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $file['data'],
                $file['name'],
            )->withMime($file['mime'] ?? 'application/octet-stream'),
            array_values($this->files),
        );
    }
}
