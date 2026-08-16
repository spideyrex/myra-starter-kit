<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

/**
 * Authored prose. The only section whose value reaches the DOM through v-html,
 * so it is sanitised on write (HtmlSanitizer) and again on render (DOMPurify).
 */
final class RichTextSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('rich_text')
            ->icon('Type')
            ->group('content')
            ->titleField('heading')
            ->fields(
                SectionField::text('heading')
                    ->labelKey('pageBuilder.sections.rich_text.fields.heading')
                    ->max(160),
                SectionField::html('body')
                    ->labelKey('pageBuilder.sections.rich_text.fields.body')
                    ->max(20000),
            )
            ->variants([
                'width' => ['prose', 'wide'],
                'tinted' => 'bool',
            ])
            ->since('2.7.0');
    }
}
