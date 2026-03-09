<?php

namespace Database\Seeders;

use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomepageSettings;
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
            ['group' => 'general', 'name' => 'site_name', 'payload' => json_encode('Myra Dashboard')],
            ['group' => 'general', 'name' => 'site_description', 'payload' => json_encode('A powerful admin dashboard')],
            ['group' => 'general', 'name' => 'site_url', 'payload' => json_encode(config('app.url'))],
            ['group' => 'general', 'name' => 'admin_email', 'payload' => json_encode('admin@admin.com')],
            ['group' => 'general', 'name' => 'timezone', 'payload' => json_encode('UTC')],

            ['group' => 'seo', 'name' => 'meta_title', 'payload' => json_encode('Myra Dashboard')],
            ['group' => 'seo', 'name' => 'meta_description', 'payload' => json_encode('Admin panel for managing your application')],
            ['group' => 'seo', 'name' => 'meta_keywords', 'payload' => json_encode('admin, dashboard')],
            ['group' => 'seo', 'name' => 'google_analytics_id', 'payload' => json_encode(null)],

            ['group' => 'appearance', 'name' => 'primary_color', 'payload' => json_encode('#18181b')],
            ['group' => 'appearance', 'name' => 'theme', 'payload' => json_encode('zinc')],
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

            // Homepage
            ['group' => 'homepage', 'name' => 'enabled', 'payload' => json_encode(true)],
            ['group' => 'homepage', 'name' => 'hero_title', 'payload' => json_encode('Build Something Amazing')],
            ['group' => 'homepage', 'name' => 'hero_subtitle', 'payload' => json_encode('The modern platform for building powerful web applications. Ship faster, scale effortlessly, and delight your users.')],
            ['group' => 'homepage', 'name' => 'hero_cta_text', 'payload' => json_encode('Get Started')],
            ['group' => 'homepage', 'name' => 'hero_cta_url', 'payload' => json_encode('/register')],
            ['group' => 'homepage', 'name' => 'hero_image_path', 'payload' => json_encode(null)],

            ['group' => 'homepage', 'name' => 'features_enabled', 'payload' => json_encode(true)],
            ['group' => 'homepage', 'name' => 'features_title', 'payload' => json_encode('Everything You Need')],
            ['group' => 'homepage', 'name' => 'features_subtitle', 'payload' => json_encode('Powerful features to help you build, deploy, and scale your applications.')],
            ['group' => 'homepage', 'name' => 'features', 'payload' => json_encode([
                ['icon' => 'Zap', 'title' => 'Lightning Fast', 'description' => 'Optimized for speed with built-in caching, lazy loading, and edge computing support.'],
                ['icon' => 'Shield', 'title' => 'Secure by Default', 'description' => 'Enterprise-grade security with two-factor authentication, role-based access, and audit logging.'],
                ['icon' => 'BarChart3', 'title' => 'Powerful Analytics', 'description' => 'Real-time dashboards and detailed reports to help you make data-driven decisions.'],
            ])],

            ['group' => 'homepage', 'name' => 'testimonials_enabled', 'payload' => json_encode(true)],
            ['group' => 'homepage', 'name' => 'testimonials_title', 'payload' => json_encode('Loved by Developers')],
            ['group' => 'homepage', 'name' => 'testimonials_subtitle', 'payload' => json_encode('See what our users have to say about their experience.')],
            ['group' => 'homepage', 'name' => 'testimonials', 'payload' => json_encode([
                ['name' => 'Sarah Chen', 'role' => 'CTO at TechFlow', 'quote' => 'This platform has transformed how we build and ship products. The admin panel alone saved us months of development time.'],
                ['name' => 'Marcus Johnson', 'role' => 'Lead Developer at Startup Co', 'quote' => 'The best developer experience I\'ve had. Everything just works, and the documentation is outstanding.'],
                ['name' => 'Emily Rodriguez', 'role' => 'Founder of DevStudio', 'quote' => 'We migrated from a custom solution and couldn\'t be happier. The built-in features cover 90% of what we need.'],
            ])],

            ['group' => 'homepage', 'name' => 'pricing_enabled', 'payload' => json_encode(true)],
            ['group' => 'homepage', 'name' => 'pricing_title', 'payload' => json_encode('Simple, Transparent Pricing')],
            ['group' => 'homepage', 'name' => 'pricing_subtitle', 'payload' => json_encode('Choose the plan that fits your needs. No hidden fees, cancel anytime.')],
            ['group' => 'homepage', 'name' => 'pricing_plans', 'payload' => json_encode([
                ['name' => 'Free', 'price' => '$0', 'period' => '/month', 'features' => 'Up to 3 users,1 GB storage,Community support,Basic analytics', 'cta_text' => 'Get Started', 'cta_url' => '/register', 'highlighted' => false],
                ['name' => 'Pro', 'price' => '$29', 'period' => '/month', 'features' => 'Unlimited users,50 GB storage,Priority support,Advanced analytics,Custom branding,API access', 'cta_text' => 'Start Free Trial', 'cta_url' => '/register', 'highlighted' => true],
                ['name' => 'Enterprise', 'price' => '$99', 'period' => '/month', 'features' => 'Unlimited everything,500 GB storage,Dedicated support,Custom integrations,SLA guarantee,SSO & SAML', 'cta_text' => 'Contact Sales', 'cta_url' => '/register', 'highlighted' => false],
            ])],

            ['group' => 'homepage', 'name' => 'cta_enabled', 'payload' => json_encode(true)],
            ['group' => 'homepage', 'name' => 'cta_title', 'payload' => json_encode('Ready to Get Started?')],
            ['group' => 'homepage', 'name' => 'cta_subtitle', 'payload' => json_encode('Join thousands of developers who are already building with our platform.')],
            ['group' => 'homepage', 'name' => 'cta_button_text', 'payload' => json_encode('Start Building for Free')],
            ['group' => 'homepage', 'name' => 'cta_button_url', 'payload' => json_encode('/register')],

            ['group' => 'homepage', 'name' => 'footer_text', 'payload' => json_encode('Building the future of web applications.')],
            ['group' => 'homepage', 'name' => 'footer_links', 'payload' => json_encode([
                ['label' => 'Privacy Policy', 'url' => '/pages/privacy-policy'],
                ['label' => 'Terms of Service', 'url' => '/pages/terms-of-service'],
            ])],

            ['group' => 'homepage', 'name' => 'navbar_cta_text', 'payload' => json_encode('Get Started')],
            ['group' => 'homepage', 'name' => 'navbar_cta_url', 'payload' => json_encode('/register')],
            ['group' => 'homepage', 'name' => 'navbar_links', 'payload' => json_encode([
                ['label' => 'Features', 'url' => '#features'],
                ['label' => 'Pricing', 'url' => '#pricing'],
                ['label' => 'Testimonials', 'url' => '#testimonials'],
            ])],

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
