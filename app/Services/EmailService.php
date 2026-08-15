<?php

namespace App\Services;

use App\Jobs\SendQueuedTemplateMail;
use App\Mail\ScheduledReportMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Settings\EmailSettings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function sendTemplate(string $slug, string $to, array $variables = []): void
    {
        $template = EmailTemplate::where('slug', $slug)->firstOrFail();

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
     * @param  string|array<int,string>  $to
     * @param  array<int, array{data:string, name:string, mime?:string}>  $attachments
     */
    public function queueTemplate(string $slug, string|array $to, array $variables = [], array $attachments = []): void
    {
        $template = EmailTemplate::where('slug', $slug)->firstOrFail();

        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body_html, $variables);

        foreach ((array) $to as $address) {
            $log = EmailLog::create([
                'to' => $address,
                'subject' => $subject,
                'template_slug' => $slug,
                'status' => 'queued',
            ]);

            SendQueuedTemplateMail::dispatch((string) $address, $subject, $body, $attachments, (int) $log->id);
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
        return Mail::build([
            'transport' => $settings->mail_mailer ?: 'smtp',
            'host' => $settings->mail_host,
            'port' => $settings->mail_port,
            'encryption' => $settings->mail_encryption,
            'username' => $settings->mail_username,
            'password' => $settings->mail_password,
            'from' => [
                'address' => $settings->mail_from_address ?: config('mail.from.address'),
                'name' => $settings->mail_from_name ?: config('mail.from.name'),
            ],
        ]);
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", (string) $value, $content);
        }

        return $content;
    }
}
