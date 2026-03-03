<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
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

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return $content;
    }
}
