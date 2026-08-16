<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class DocsTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('docs')
            ->component('Public/Templates/Docs')
            ->thumbnail('/images/templates/docs.svg')
            ->supports('hero', 'features', 'pricing', 'cta')
            ->since('2.6.0');
    }
}
