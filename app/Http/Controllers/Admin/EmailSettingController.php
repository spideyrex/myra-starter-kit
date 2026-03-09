<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingController extends Controller
{
    public function index(): Response
    {
        $settings = app(EmailSettings::class);

        return Inertia::render('Admin/Email/Settings', [
            'settings' => [
                'mail_mailer' => $settings->mail_mailer,
                'mail_host' => $settings->mail_host,
                'mail_port' => $settings->mail_port,
                'mail_username' => $settings->mail_username,
                'mail_encryption' => $settings->mail_encryption,
                'mail_from_address' => $settings->mail_from_address,
                'mail_from_name' => $settings->mail_from_name,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mail_mailer' => 'required|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
        ]);

        $settings = app(EmailSettings::class);
        $settings->mail_mailer = $request->mail_mailer;
        $settings->mail_host = $request->mail_host;
        $settings->mail_port = $request->mail_port;
        $settings->mail_username = $request->mail_username;
        if ($request->mail_password) {
            $settings->mail_password = $request->mail_password;
        }
        $settings->mail_encryption = $request->mail_encryption;
        $settings->mail_from_address = $request->mail_from_address;
        $settings->mail_from_name = $request->mail_from_name;
        $settings->save();

        return back()->with('success', 'Email settings updated successfully.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        // Apply runtime mail config from settings
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

        try {
            Mail::raw('This is a test email from your application.', function ($message) use ($request) {
                $message->to($request->email)->subject('Test Email');
            });

            return back()->with('success', 'Test email sent successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }
}
