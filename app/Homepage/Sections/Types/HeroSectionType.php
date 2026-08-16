<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class HeroSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('hero')
            ->icon('Rocket')
            ->group('content')
            ->titleField('title')
            ->maxPerPage(1)
            ->fields(
                SectionField::text('title'),
                SectionField::textarea('subtitle'),
                SectionField::text('cta_text'),
                SectionField::url('cta_url'),
                SectionField::image('image_path'),
            )
            ->variants([
                'align' => ['center', 'split', 'left'],
                'compact' => 'bool',
            ])
            ->since('2.7.0');
    }
}
