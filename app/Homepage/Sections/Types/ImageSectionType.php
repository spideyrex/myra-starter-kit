<?php

namespace App\Homepage\Sections\Types;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

/**
 * A single figure. `alt` is required — accessibility does not erode one
 * authored page at a time.
 */
final class ImageSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('image')
            ->icon('Image')
            ->group('content')
            ->titleField('caption')
            ->fields(
                SectionField::image('image_path')
                    ->labelKey('pageBuilder.sections.image.fields.image_path'),
                SectionField::text('alt')
                    ->labelKey('pageBuilder.sections.image.fields.alt')
                    ->required()
                    ->max(160),
                SectionField::text('caption')
                    ->labelKey('pageBuilder.sections.image.fields.caption')
                    ->max(200),
                SectionField::url('link_url')
                    ->labelKey('pageBuilder.sections.image.fields.link_url'),
            )
            ->variants([
                'width' => ['contained', 'full'],
                'rounded' => 'bool',
            ])
            ->since('2.7.0');
    }
}
