<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

/** Question/answer pairs, rendered as a single-open accordion. */
final class FaqSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('faq')
            ->icon('CircleHelp')
            ->group('content')
            ->titleField('title')
            ->fields(
                SectionField::text('title')
                    ->labelKey('pageBuilder.sections.faq.fields.title')
                    ->max(160),
                SectionField::textarea('subtitle')
                    ->labelKey('pageBuilder.sections.faq.fields.subtitle')
                    ->max(400),
                SectionField::list(
                    'items',
                    SectionField::text('question')
                        ->labelKey('pageBuilder.sections.faq.itemFields.question')
                        ->max(200),
                    SectionField::textarea('answer')
                        ->labelKey('pageBuilder.sections.faq.itemFields.answer')
                        ->max(2000),
                )
                    ->labelKey('pageBuilder.sections.faq.fields.items')
                    ->max(20),
            )
            ->variants(['tinted' => 'bool'])
            ->since('2.7.0');
    }
}
