<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = EmailLog::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Email/Log', [
            'logs' => EmailLogResource::collection($logs),
            'filters' => $request->only(['search', 'status']),
            'statuses' => EmailLog::distinct()->pluck('status')->sort()->values(),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        EmailLog::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected email logs deleted.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $logs = EmailLog::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%");
            }))
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'To', 'Subject', 'Template', 'Status', 'Sent At']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->to,
                    $log->subject,
                    $log->template_slug,
                    $log->status,
                    $log->sent_at,
                ]);
            }

            fclose($file);
        }, 'email-log.csv');
    }
}
