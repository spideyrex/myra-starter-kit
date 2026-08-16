<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class FeaturesSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('features')
            ->icon('Grid3x3')
            ->group('content')
            ->titleField('title')
            ->fields(
                SectionField::text('title'),
                SectionField::textarea('subtitle'),
                SectionField::list(
                    'items',
                    SectionField::icon('icon'),
                    SectionField::text('title'),
                    SectionField::textarea('description'),
                )->max(12),
            )
            ->variants([
                'columns' => ['3', '2'],
                'bare' => 'bool',
            ])
            ->since('2.7.0');
    }
}
