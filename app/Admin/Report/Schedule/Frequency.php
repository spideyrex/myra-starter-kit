<?php

namespace App\Admin\Report\Schedule;

/**
 * A closed preset set, never a user-supplied cron string: an arbitrary
 * expression stored in a row a queue worker evaluates on a timer is a DoS
 * vector wearing a convenience costume.
 */
enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function labelKey(): string
    {
        return 'reportDelivery.frequency.'.$this->value;
    }

    public function needsDayOfWeek(): bool
    {
        return $this === self::Weekly;
    }

    public function needsDayOfMonth(): bool
    {
        return $this === self::Monthly || $this === self::Quarterly;
    }
}
