<?php

namespace App\Admin\Traits;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportableQuery
{
    /**
     * Stream a CSV export from a query builder.
     *
     * @param  Builder  $query
     * @param  array<string>  $headers  Column headers
     * @param  callable  $rowMapper  fn($model): array — maps a model to a CSV row
     * @param  string  $filename
     */
    protected function streamCsvExport(
        Builder $query,
        array $headers,
        callable $rowMapper,
        string $filename = 'export.csv',
    ): StreamedResponse {
        return response()->streamDownload(function () use ($query, $headers, $rowMapper) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            $query->chunk(500, function ($records) use ($file, $rowMapper) {
                foreach ($records as $record) {
                    fputcsv($file, $rowMapper($record));
                }
            });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
