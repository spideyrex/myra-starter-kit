<?php

namespace App\Admin\Report\Contracts;

use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Implemented by the report-delivery bundle. Until something is bound,
 * `format=pdf` is a 422 with `reports.errors.formatUnavailable`.
 */
interface ReportDocumentRenderer
{
    /** @return string[] e.g. ['pdf'] */
    public static function formats(): array;

    public function stream(
        ReportDefinition $d,
        ReportRequest $r,
        ReportResult $res,
        array $chartImages = [],
    ): StreamedResponse;

    /** @return string path relative to the given disk */
    public function writeToDisk(
        ReportDefinition $d,
        ReportRequest $r,
        ReportResult $res,
        string $disk = 'local',
    ): string;
}
