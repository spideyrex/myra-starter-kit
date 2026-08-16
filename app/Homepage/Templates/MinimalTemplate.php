<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class MinimalTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('minimal')
            ->component('Public/Templates/Minimal')
            ->thumbnail('/images/templates/minimal.svg')
            ->supports('hero', 'features', 'cta')
            ->since('2.6.0');
    }
}
