<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class EditorialTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('editorial')
            ->component('Public/Templates/Editorial')
            ->thumbnail('/images/templates/editorial.svg')
            ->supports('hero', 'features', 'testimonials', 'cta')
            ->since('2.6.0');
    }
}
