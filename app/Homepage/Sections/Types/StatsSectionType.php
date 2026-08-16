<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

/** Up to four headline numbers. */
final class StatsSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('stats')
            ->icon('TrendingUp')
            ->group('proof')
            ->titleField('title')
            ->fields(
                SectionField::text('title')
                    ->labelKey('pageBuilder.sections.stats.fields.title')
                    ->max(160),
                SectionField::list(
                    'items',
                    SectionField::text('value')
                        ->labelKey('pageBuilder.sections.stats.itemFields.value')
                        ->max(24),
                    SectionField::text('label')
                        ->labelKey('pageBuilder.sections.stats.itemFields.label')
                        ->max(80),
                )
                    ->labelKey('pageBuilder.sections.stats.fields.items')
                    ->max(4),
            )
            ->variants(['tinted' => 'bool'])
            ->since('2.7.0');
    }
}
