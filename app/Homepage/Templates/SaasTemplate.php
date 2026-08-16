<?php

namespace App\Homepage\Templates;

use App\Homepage\HomepageTemplate;

final class SaasTemplate
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('saas')
            ->component('Public/Templates/SaaS')
            ->thumbnail('/images/templates/saas.svg')
            ->supports('hero', 'pricing', 'features', 'testimonials', 'cta')
            ->since('2.6.0');
    }
}
