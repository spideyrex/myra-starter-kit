<?php

namespace Database\Seeders;

use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use App\Settings\MaintenanceSettings;
use App\Settings\SeoSettings;
use App\Settings\SocialSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'name' => 'site_name', 'payload' => json_encode('Admin Dashboard')],
            ['group' => 'general', 'name' => 'site_description', 'payload' => json_encode('A powerful admin dashboard')],
            ['group' => 'general', 'name' => 'site_url', 'payload' => json_encode(config('app.url'))],
            ['group' => 'general', 'name' => 'admin_email', 'payload' => json_encode('admin@admin.com')],
            ['group' => 'general', 'name' => 'timezone', 'payload' => json_encode('UTC')],

            ['group' => 'seo', 'name' => 'meta_title', 'payload' => json_encode('Admin Dashboard')],
            ['group' => 'seo', 'name' => 'meta_description', 'payload' => json_encode('Admin panel for managing your application')],
            ['group' => 'seo', 'name' => 'meta_keywords', 'payload' => json_encode('admin, dashboard')],
            ['group' => 'seo', 'name' => 'google_analytics_id', 'payload' => json_encode(null)],

            ['group' => 'appearance', 'name' => 'primary_color', 'payload' => json_encode('#18181b')],
            ['group' => 'appearance', 'name' => 'logo_path', 'payload' => json_encode(null)],
            ['group' => 'appearance', 'name' => 'favicon_path', 'payload' => json_encode(null)],

            ['group' => 'social', 'name' => 'facebook_url', 'payload' => json_encode(null)],
            ['group' => 'social', 'name' => 'twitter_url', 'payload' => json_encode(null)],
            ['group' => 'social', 'name' => 'instagram_url', 'payload' => json_encode(null)],
            ['group' => 'social', 'name' => 'linkedin_url', 'payload' => json_encode(null)],

            ['group' => 'maintenance', 'name' => 'enabled', 'payload' => json_encode(false)],
            ['group' => 'maintenance', 'name' => 'message', 'payload' => json_encode('We are currently performing maintenance. Please check back later.')],

            ['group' => 'email', 'name' => 'mail_mailer', 'payload' => json_encode(config('mail.default', 'smtp'))],
            ['group' => 'email', 'name' => 'mail_host', 'payload' => json_encode(config('mail.mailers.smtp.host'))],
            ['group' => 'email', 'name' => 'mail_port', 'payload' => json_encode(config('mail.mailers.smtp.port', 587))],
            ['group' => 'email', 'name' => 'mail_username', 'payload' => json_encode(config('mail.mailers.smtp.username'))],
            ['group' => 'email', 'name' => 'mail_password', 'payload' => json_encode(null)],
            ['group' => 'email', 'name' => 'mail_encryption', 'payload' => json_encode(config('mail.mailers.smtp.encryption', 'tls'))],
            ['group' => 'email', 'name' => 'mail_from_address', 'payload' => json_encode(config('mail.from.address'))],
            ['group' => 'email', 'name' => 'mail_from_name', 'payload' => json_encode(config('mail.from.name'))],

            ['group' => 'firebase', 'name' => 'enabled', 'payload' => json_encode(false)],
            ['group' => 'firebase', 'name' => 'service_account_path', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'api_key', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'auth_domain', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'project_id', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'storage_bucket', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'messaging_sender_id', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'app_id', 'payload' => json_encode(null)],
            ['group' => 'firebase', 'name' => 'vapid_key', 'payload' => json_encode(null)],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'name' => $setting['name']],
                array_merge($setting, ['locked' => false, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
