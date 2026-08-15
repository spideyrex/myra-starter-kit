<?php

namespace App\Admin\Report;

enum PeriodPreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';
    case ThisQuarter = 'this_quarter';
    case ThisYear = 'this_year';
    case LastWeek = 'last_week';
    case LastMonth = 'last_month';
    case LastQuarter = 'last_quarter';
    case LastYear = 'last_year';
    case Custom = 'custom';

    public function labelKey(): string
    {
        return 'reports.period.' . $this->value;
    }
}
