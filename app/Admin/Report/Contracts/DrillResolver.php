<?php

namespace App\Admin\Report\Contracts;

use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRow;

/**
 * Implemented by the report-delivery bundle. Resolved optionally, so a row's
 * `drill` is simply null when nothing is bound.
 */
interface DrillResolver
{
    /** @return array{url: string, params: array<string,string>}|null */
    public function forRow(
        ReportDefinition $definition,
        ReportRequest $request,
        ReportRow $row,
    ): ?array;
}
