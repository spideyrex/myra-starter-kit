<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class CtaSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('cta')
            ->icon('Megaphone')
            ->group('conversion')
            ->titleField('title')
            ->fields(
                SectionField::text('title'),
                SectionField::textarea('subtitle'),
                SectionField::text('button_text'),
                SectionField::url('button_url'),
            )
            ->variants(['muted' => 'bool'])
            ->since('2.7.0');
    }
}
