<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Export\CsvRowWriter;
use App\Admin\Export\XlsxRowWriter;
use App\Admin\Report\Contracts\ReportDocumentRenderer;
use App\Admin\Report\ReportBatch;
use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportException;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportResult;
use App\Admin\Report\ReportRow;
use App\Admin\Traits\RunsReports;
use App\Http\Controllers\Controller;
use App\Models\TableView;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use RunsReports;

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');

        return Inertia::render('Admin/Reports/Index', [
            'reports' => array_values(ReportRegistry::visibleTo($request->user())),
        ]);
    }

    public function show(Request $request, string $report): Response
    {
        $definition = ReportRegistry::resolve($report);
        $result = $this->runReport($request, $definition);

        return Inertia::render('Admin/Reports/Show', [
            'schema' => $definition->toClientSchema($request->user()),
            'state' => $result->request()->toArray(),
            'result' => $result->toArray(),
            'savedViews' => $this->savedViews($request, $definition),
            'canShareViews' => (bool) $request->user()?->current_team_id,
        ]);
    }

    /** POST: a rule tree does not fit in a URL. */
    public function data(Request $request, string $report): JsonResponse
    {
        $definition = ReportRegistry::resolve($report);

        return response()->json($this->runReport($request, $definition)->toArray());
    }

    /** POST: one request for a whole dashboard, never one per widget. */
    public function widgets(Request $request): JsonResponse
    {
        Gate::authorize('reports.view');

        $specs = $request->input('widgets');

        if (! is_array($specs)) {
            throw ReportException::make('reports.errors.malformed');
        }

        // >>> MYRA v2.5 [B] START
        // Opaque, server-minted strings echoed back by the client. A forged or
        // stale value can only ever cost a re-run, never disclose anything.
        $versions = $request->input('versions', []);
        $versions = is_array($versions) ? array_filter($versions, 'is_string') : [];
        // <<< MYRA v2.5 [B] END

        return response()->json([
            'results' => ReportBatch::run($specs, $request->user(), $versions),
        ]);
    }

    /** Exports the BUCKETS, not the source rows — the result is already capped. */
    public function export(Request $request, string $report): StreamedResponse
    {
        Gate::authorize('reports.export');

        $definition = ReportRegistry::resolve($report);
        $result = $this->runReport($request, $definition);

        $format = in_array($request->input('format'), $definition->allowedFormats(), true)
            ? (string) $request->input('format')
            : 'csv';

        if ($format === 'pdf') {
            if (! app()->bound(ReportDocumentRenderer::class)) {
                throw ReportException::make('reports.errors.formatUnavailable');
            }

            return app(ReportDocumentRenderer::class)
                ->stream($definition, $result->request(), $result);
        }

        if ($format === 'xlsx' && ! XlsxRowWriter::isSupported()) {
            $format = 'csv';
        }

        [$headers, $rows] = $this->tabulate($result);

        // SEAM: post-merge this becomes WriterRegistry::make()/contentType(),
        // which is the single authority on export formats once bundle A lands.
        return response()->streamDownload(function () use ($format, $definition, $headers, $rows) {
            $writer = $format === 'xlsx' ? new XlsxRowWriter($definition->key()) : new CsvRowWriter;

            $writer->open();
            $writer->header($headers);

            foreach ($rows as $row) {
                $writer->row($row);
            }

            $writer->close();
        }, Csv::filename('report-' . $definition->key(), $format), [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @return array{0: string[], 1: array<int, array<int, mixed>>} */
    private function tabulate(ReportResult $result): array
    {
        $request = $result->request();
        $measures = $request->measures();
        $comparing = $request->comparisonPeriod() !== null;

        $headers = [self::label($request->dimension()->toClientSchema()['labelKey'])];

        foreach ($measures as $m) {
            $label = self::label($m->toClientSchema()['labelKey']);
            $headers[] = $label;

            if ($comparing) {
                $headers[] = $label . ' — ' . self::label('reports.export.previous');
                $headers[] = $label . ' — ' . self::label('reports.export.change');
            }
        }

        $rows = [];

        foreach ($result->rows() as $row) {
            /** @var ReportRow $row */
            $line = [$row->isOther ? self::label('reports.other') : $row->label];

            foreach ($measures as $m) {
                $line[] = $row->values[$m->key] ?? null;

                if ($comparing) {
                    $line[] = $row->previous[$m->key] ?? null;
                    $delta = $row->deltas[$m->key] ?? null;
                    $line[] = $delta === null ? null : $delta['absolute'];
                }
            }

            $rows[] = $line;
        }

        return [$headers, $rows];
    }

    /**
     * i18n keys resolve client-side (the `filters.errors.*` convention), so an
     * export — which has no client — humanises the key when PHP has no
     * translation for it.
     */
    private static function label(string $key): string
    {
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : \Illuminate\Support\Str::headline(\Illuminate\Support\Str::afterLast($key, '.'));
    }

    /** Saved report views reuse table_views with a `report:` prefixed key — zero new tables. */
    private function savedViews(Request $request, ReportDefinition $definition): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return TableView::visibleTo($user)
            ->where('table_key', 'report:' . $definition->key())
            ->where('name', '!=', TableView::COLUMNS_NAME)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (TableView $view) => $view->toClientArray($user))
            ->values()
            ->all();
    }
}
