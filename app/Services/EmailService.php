<?php

namespace App\Services;

use App\Jobs\SendQueuedTemplateMail;
use App\Mail\ScheduledReportMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Settings\EmailSettings;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailService
{
    public function sendTemplate(string $slug, string $to, array $variables = []): void
    {
        $template = $this->template($slug);

        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body_html, $variables);

        $log = EmailLog::create([
            'to' => $to,
            'subject' => $subject,
            'template_slug' => $slug,
            'status' => 'queued',
        ]);

        try {
            $this->deliver($to, $subject, $body);

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Queued counterpart. The EmailLog row's 'queued' status finally means what
     * it says: nothing is transmitted inside the request.
     *
     * Attachment bodies are base64-encoded on the way into the job payload: a
     * queue payload is json_encode()d, and raw PDF/xlsx bytes are not valid
     * UTF-8, so the push would fail outright. The worker decodes them again.
     *
     * @param  string|array<int,string>  $to
     * @param  array<int, array{data:string, name:string, mime?:string}>  $attachments  raw bytes
     */
    public function queueTemplate(string $slug, string|array $to, array $variables = [], array $attachments = []): void
    {
        $template = $this->template($slug);

        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body_html, $variables);

        $encoded = array_map(
            static fn (array $a) => array_merge($a, ['data' => base64_encode((string) $a['data'])]),
            array_values($attachments),
        );

        foreach ((array) $to as $address) {
            $log = EmailLog::create([
                'to' => $address,
                'subject' => $subject,
                'template_slug' => $slug,
                'status' => 'queued',
            ]);

            SendQueuedTemplateMail::dispatch((string) $address, $subject, $body, $encoded, (int) $log->id);
        }
    }

    public function sendTemplateTest(EmailTemplate $template, string $to, array $variables = []): void
    {
        $subject = '[Test] ' . $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body_html, $variables);

        $this->deliver($to, $subject, $body);
    }

    /**
     * Sends through a mailer built from EmailSettings, synchronously.
     *
     * @param  array<int, array{data:string, name:string, mime?:string}>  $attachments
     */
    public function deliver(string $to, string $subject, string $body, array $attachments = []): void
    {
        $this->mailer()->send(
            (new ScheduledReportMail($subject, $body, $attachments))->to($to),
        );
    }

    /** Public so a queue worker can build the same mailer at handle time. */
    public function mailer(): Mailer
    {
        return $this->mailerFor(app(EmailSettings::class));
    }

    /**
     * Builds a Mailer from EmailSettings WITHOUT touching global config.
     *
     * The old applyMailConfig() mutated config() and purged the smtp manager per
     * send. In a queue worker that config bleeds across every job in the same
     * process, so the last schedule to run decided everyone's SMTP host.
     */
    private function mailerFor(EmailSettings $settings): Mailer
    {
        $mailer = Mail::build([
            'transport' => $settings->mail_mailer ?: 'smtp',
            'host' => $settings->mail_host,
            'port' => $settings->mail_port,
            'encryption' => $settings->mail_encryption,
            'username' => $settings->mail_username,
            'password' => $settings->mail_password,
        ]);

        // MailManager::build() only wires the transport — the global addresses are
        // set in resolve(), which build() never reaches. So apply "from" here.
        $mailer->alwaysFrom(
            (string) ($settings->mail_from_address ?: config('mail.from.address')),
            $settings->mail_from_name ?: config('mail.from.name'),
        );

        return $mailer;
    }

    private function template(string $slug): EmailTemplate
    {
        $template = EmailTemplate::where('slug', $slug)->first();

        if ($template === null) {
            throw new RuntimeException(__('reportDelivery.errors.missingTemplate', ['slug' => $slug]));
        }

        return $template;
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", (string) $value, $content);
        }

        return $content;
    }
}
