<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

/**
 * Breathing room between sections. Both fields are selects, so the first
 * declared option is the default — never '' (reka-ui rejects an empty value).
 */
final class DividerSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('divider')
            ->icon('Minus')
            ->group('layout')
            ->fields(
                SectionField::select('style', ['rule', 'space'])
                    ->labelKey('pageBuilder.sections.divider.fields.style'),
                SectionField::select('size', ['sm', 'md', 'lg'])
                    ->labelKey('pageBuilder.sections.divider.fields.size'),
            )
            ->since('2.7.0');
    }
}
