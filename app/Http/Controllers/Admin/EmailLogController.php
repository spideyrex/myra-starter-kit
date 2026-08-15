<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Export\ExportColumn;
use App\Admin\Export\ExportDefinition;
use App\Admin\Traits\ExportableQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogController extends Controller
{
    use ExportableQuery;

    /** One filter definition shared by the listing and the export. */
    private function baseQuery(Request $request): Builder
    {
        return EmailLog::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%");
            }));
    }

    public function index(Request $request): Response
    {
        $logs = $this->baseQuery($request)
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
        Gate::authorize('email.view');

        return $this->streamExport(
            $this->baseQuery($request),
            ExportDefinition::make('email-log')
                ->columns([
                    ExportColumn::make('id')->label('ID'),
                    ExportColumn::make('to')->label('To'),
                    ExportColumn::make('subject')->label('Subject'),
                    ExportColumn::make('template_slug')->label('Template'),
                    ExportColumn::make('status')->label('Status'),
                    ExportColumn::make('sent_at')->label('Sent At'),
                ])
                ->formats(['csv', 'xlsx'])
                ->maxRows((int) config('myra.exports.max_rows', 50000))
                ->filename('email-log'),
            $request,
        );
    }
}
