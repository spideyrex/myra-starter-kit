<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class TestimonialsSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('testimonials')
            ->icon('Quote')
            ->group('proof')
            ->titleField('title')
            ->fields(
                SectionField::text('title'),
                SectionField::textarea('subtitle'),
                SectionField::list(
                    'items',
                    SectionField::text('name'),
                    SectionField::text('role'),
                    SectionField::textarea('quote'),
                )->max(12),
            )
            ->variants(['tinted' => 'bool'])
            ->since('2.7.0');
    }
}
