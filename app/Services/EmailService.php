<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Settings\EmailSettings;
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

        // Apply admin-configured SMTP settings from database
        $this->applyMailConfig();

        try {
            Mail::html($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply runtime mail configuration from admin-stored settings.
     */
    private function applyMailConfig(): void
    {
        $settings = app(EmailSettings::class);

        config([
            'mail.default' => $settings->mail_mailer,
            'mail.mailers.smtp.host' => $settings->mail_host,
            'mail.mailers.smtp.port' => $settings->mail_port,
            'mail.mailers.smtp.username' => $settings->mail_username,
            'mail.mailers.smtp.password' => $settings->mail_password,
            'mail.mailers.smtp.encryption' => $settings->mail_encryption,
            'mail.from.address' => $settings->mail_from_address,
            'mail.from.name' => $settings->mail_from_name,
        ]);

        // Purge cached transport so new settings take effect
        app('mail.manager')->purge('smtp');
    }

    public function sendTemplateTest(EmailTemplate $template, string $to, array $variables = []): void
    {
        $this->applyMailConfig();

        $subject = '[Test] ' . $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body_html, $variables);

        Mail::html($body, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return $content;
    }
}
