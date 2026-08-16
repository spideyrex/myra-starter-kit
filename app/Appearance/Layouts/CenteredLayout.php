<?php

namespace App\Appearance\Layouts;

use App\Appearance\AuthLayout;

final class CenteredLayout
{
    public static function define(): AuthLayout
    {
        return AuthLayout::make('centered')
            ->component('CenteredLayout')
            ->flippable(false)
            ->supportsMedia(false)
            ->since('2.8.0');
    }
}
