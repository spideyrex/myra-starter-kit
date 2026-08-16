<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Attachments travel as bytes, not paths: the rendered document is deleted the
 * moment the mailable is built, so nothing lingers on disk waiting for a worker.
 *
 * Deliberately NOT ShouldQueue: SendQueuedTemplateMail already owns the queueing
 * and hands this to an on-demand mailer, which Mailer::sendMailable() would
 * otherwise re-queue under the undefined name "ondemand".
 */
final class ScheduledReportMail extends BrandedMailable
{
    use Queueable, SerializesModels;

    /**
     * `$bodyHtml`, not `$html`: Mailable already declares a non-readonly $html,
     * and redeclaring it readonly is a fatal at class load.
     *
     * @param  array<int, array{data:string, name:string, mime?:string}>  $files
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyHtml,
        public readonly array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        // >>> MYRA v2.6 [C] START
        return $this->brandView($this->bodyHtml, ['subjectLine' => $this->subjectLine]);
        // <<< MYRA v2.6 [C] END
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
