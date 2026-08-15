<?php

namespace App\Admin\Traits;

use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportResult;
use App\Admin\Report\ReportRunner;
use App\Admin\Report\StatResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait RunsReports
{
    protected function runReport(Request $r, ReportDefinition $def, string $param = 'state'): ReportResult
    {
        Gate::authorize($def->permissionAbility());

        return (new ReportRunner($def))
            ->run(ReportRequest::parse($r->input($param), $def, $r->user()), $r->user());
    }

    protected function runReportStat(Request $r, ReportDefinition $def, string $param = 'state'): StatResult
    {
        Gate::authorize($def->permissionAbility());

        return (new ReportRunner($def))
            ->stat(ReportRequest::parse($r->input($param), $def, $r->user()), $r->user());
    }
}
