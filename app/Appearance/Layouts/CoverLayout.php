<?php

namespace App\Appearance\Layouts;

use App\Appearance\AuthLayout;

final class CoverLayout
{
    public static function define(): AuthLayout
    {
        return AuthLayout::make('cover')
            ->component('CoverLayout')
            ->flippable(false)
            ->supportsMedia()
            ->since('2.8.0');
    }
}
