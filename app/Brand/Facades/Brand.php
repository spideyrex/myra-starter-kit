<?php

namespace App\Brand\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Brand\Brand current()
 * @method static void forget()
 * @method static \Illuminate\Support\HtmlString styleTag()
 * @method static \Illuminate\Support\HtmlString bootScriptTag()
 * @method static \Illuminate\Support\HtmlString iconLinks()
 * @method static string hash()
 * @method static array mailTokens()
 * @method static array pdfMeta()
 * @method static array manifest()
 * @method static array metaTags()
 * @method static array toInertiaProp()
 * @method static string|null logoBytes(string $variant = 'email')
 *
 * @see \App\Brand\BrandManager
 */
class Brand extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Brand\BrandManager::class;
    }
}
