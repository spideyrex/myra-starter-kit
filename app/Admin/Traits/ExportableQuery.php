<?php

namespace App\Admin\Traits;

use App\Admin\Export\CsvRowWriter;
use App\Admin\Export\ExportDefinition;
use App\Admin\Export\XlsxRowWriter;
use App\Admin\Http\Refusal;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportableQuery
{
    /**
     * Legacy entry point, kept for existing adopters (make:myra-export output
     * before v2.2). Now iterates by key instead of by OFFSET.
     *
     * @param  array<string>  $headers  Column headers
     * @param  callable  $rowMapper  fn($model): array — maps a model to a CSV row
     */
    protected function streamCsvExport(
        Builder $query,
        array $headers,
        callable $rowMapper,
        string $filename = 'export.csv',
    ): StreamedResponse {
        return response()->streamDownload(function () use ($query, $headers, $rowMapper) {
            @set_time_limit(0);
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            // lazyById() is KEYSET iteration: constant cost per chunk and stable
            // under concurrent writes. chunk() is OFFSET paging — it skips and
            // duplicates rows when the data mutates mid-export.
            foreach ($query->lazyById(500) as $record) {
                fputcsv($file, Csv::row($rowMapper($record)));
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Primary entry point. Streams a declared export from a scoped query without
     * ever materialising the full result set.
     *
     * The `$query` passed in MUST already carry every ownership scope and filter
     * the on-screen listing applies — the definition adds columns, never rows.
     */
    protected function streamExport(
        Builder $query,
        ExportDefinition $definition,
        Request $request,
    ): StreamedResponse {
        $columns = $definition->selected($this->requestedColumns($request));

        $format = in_array($request->input('format'), $definition->allowedFormats(), true)
            ? (string) $request->input('format')
            : 'csv';

        if ($format === 'xlsx' && ! XlsxRowWriter::isSupported()) {
            $format = 'csv';
        }

        foreach ($definition->aggregates() as $agg) {
            $query = $agg($query);
        }

        if ($definition->eagerLoads()) {
            $query->with($definition->eagerLoads());
        }

        // Refuse rather than hand back a truncated file the user mistakes for
        // complete. Checked before streamDownload() so no partial body is emitted.
        // COUNT only — the result set is never materialised.
        $count = (clone $query)->toBase()->getCountForPagination();

        if ($count > $definition->getMaxRows()) {
            throw new HttpResponseException(Refusal::respond(
                $request,
                __('transfer.export.tooManyRows', ['max' => $definition->getMaxRows()]),
                ['max' => $definition->getMaxRows()],
            ));
        }

        return response()->streamDownload(function () use ($query, $columns, $definition, $format) {
            @set_time_limit(0);

            $writer = $format === 'xlsx' ? new XlsxRowWriter($definition->key()) : new CsvRowWriter;

            $writer->open();
            $writer->header(array_map(fn ($c) => $c->getLabel(), $columns));

            $rows = $definition->preservesSort()
                ? $query->cursor()
                : $query->lazyById($definition->getChunkSize(), $definition->getKeyColumn());

            $i = 0;
            foreach ($rows as $record) {
                $writer->row(array_map(fn ($c) => $c->resolve($record), $columns));

                if (++$i % 500 === 0) {
                    $writer->flush();
                }
            }

            $writer->close();
        }, Csv::filename($definition->filenameBase(), $format), [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** `columns` may arrive as an array or a comma-joined string from a link href. */
    private function requestedColumns(Request $request): ?array
    {
        $raw = $request->input('columns');

        if (is_string($raw)) {
            return array_filter(array_map('trim', explode(',', $raw)));
        }

        return is_array($raw) ? $raw : null;
    }
}
