<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class ClassicTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('classic')
            ->component('Public/Templates/Classic')
            ->thumbnail('/images/templates/classic.svg')
            ->supports('hero', 'features', 'testimonials', 'pricing', 'cta')
            ->since('2.6.0');
    }
}
