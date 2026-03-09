<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Email/Templates/Index', [
            'templates' => EmailTemplate::latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Email/Templates/Edit', [
            'template' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:email_templates',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        EmailTemplate::create($validated);

        return redirect()->route('admin.email-templates.index')->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $emailTemplate): Response
    {
        return Inertia::render('Admin/Email/Templates/Edit', [
            'template' => $emailTemplate,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $emailTemplate->update($validated);

        return redirect()->route('admin.email-templates.index')->with('success', 'Template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->delete();

        return redirect()->route('admin.email-templates.index')->with('success', 'Template deleted.');
    }

    public function sendTest(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'variables' => 'nullable|array',
        ]);

        try {
            app(EmailService::class)->sendTemplateTest(
                $emailTemplate,
                $request->email,
                $request->variables ?? [],
            );

            return back()->with('success', 'Test email sent to ' . $request->email);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }
}
