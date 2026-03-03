<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Welcome Email',
                'slug' => 'welcome',
                'subject' => 'Welcome to {{app_name}}',
                'body_html' => '<h1>Welcome, {{name}}!</h1><p>Thank you for joining {{app_name}}. We\'re glad to have you on board.</p><p>If you have any questions, please don\'t hesitate to reach out.</p>',
                'variables' => ['name', 'app_name'],
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password-reset',
                'subject' => 'Reset Your Password',
                'body_html' => '<h1>Password Reset Request</h1><p>Hi {{name}},</p><p>We received a request to reset your password. Click the link below to set a new password:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>If you didn\'t request this, please ignore this email.</p>',
                'variables' => ['name', 'reset_link'],
            ],
            [
                'name' => 'Email Verification',
                'slug' => 'email-verification',
                'subject' => 'Verify Your Email Address',
                'body_html' => '<h1>Verify Your Email</h1><p>Hi {{name}},</p><p>Please click the link below to verify your email address:</p><p><a href="{{verification_link}}">Verify Email</a></p>',
                'variables' => ['name', 'verification_link'],
            ],
            [
                'name' => 'Account Suspended',
                'slug' => 'account-suspended',
                'subject' => 'Your Account Has Been Suspended',
                'body_html' => '<h1>Account Suspended</h1><p>Hi {{name}},</p><p>Your account on {{app_name}} has been suspended. If you believe this is an error, please contact our support team.</p><p>Reason: {{reason}}</p>',
                'variables' => ['name', 'app_name', 'reason'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
