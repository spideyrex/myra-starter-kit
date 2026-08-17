<?php

namespace App\Appearance\Layouts;

use App\Appearance\AuthLayout;

final class CardLayout
{
    public static function define(): AuthLayout
    {
        return AuthLayout::make('card')
            ->component('CardLayout')
            ->flippable()
            ->supportsMedia()
            ->since('2.8.0');
    }
}
