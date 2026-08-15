<?php

namespace App\Admin\Report;

enum ComparisonPeriod: string
{
    case None = 'none';
    case Previous = 'previous';
    case Year = 'year';

    public function labelKey(): string
    {
        return 'reports.compare.' . $this->value;
    }
}
