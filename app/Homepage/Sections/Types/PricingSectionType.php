<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class PricingSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('pricing')
            ->icon('CreditCard')
            ->group('conversion')
            ->titleField('title')
            ->fields(
                SectionField::text('title'),
                SectionField::textarea('subtitle'),
                // `features` is a COMMA-JOINED STRING — PricingTable.vue splits it.
                SectionField::list(
                    'plans',
                    SectionField::text('name'),
                    SectionField::text('price'),
                    SectionField::text('period'),
                    SectionField::text('features'),
                    SectionField::text('cta_text'),
                    SectionField::url('cta_url'),
                    SectionField::bool('highlighted'),
                )->max(6),
            )
            ->variants(['tinted' => 'bool'])
            ->since('2.7.0');
    }
}
