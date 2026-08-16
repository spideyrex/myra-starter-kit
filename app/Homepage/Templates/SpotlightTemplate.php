<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class SpotlightTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('spotlight')
            ->component('Public/Templates/Spotlight')
            ->thumbnail('/images/templates/spotlight.svg')
            ->supports('hero', 'features', 'testimonials', 'cta')
            ->since('2.6.0');
    }
}
