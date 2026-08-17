<?php

namespace App\Appearance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Appearance\AuthAppearance auth(?\Illuminate\Http\Request $request = null)
 * @method static \App\Appearance\Background page()
 * @method static bool navbarTranslucent()
 * @method static \Illuminate\Support\HtmlString styleTag()
 * @method static array toInertiaProp(?\Illuminate\Http\Request $request = null)
 * @method static \App\Appearance\Background fromInput(array $input, string $surface)
 * @method static void forget()
 *
 * @see \App\Appearance\AppearanceManager
 */
class Appearance extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Appearance\AppearanceManager::class;
    }
}
