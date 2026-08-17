<?php

namespace App\Appearance\Layouts;

use App\Appearance\AuthLayout;

final class SplitLayout
{
    public static function define(): AuthLayout
    {
        return AuthLayout::make('split')
            ->component('SplitLayout')
            ->flippable()
            ->supportsMedia()
            ->since('2.8.0');
    }
}
