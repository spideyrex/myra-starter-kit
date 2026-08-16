<?php

namespace Tests\Feature\Plugin\Fixtures;

use App\Admin\Plugin\Manifest;
use App\Admin\Plugin\MyraPlugin;

/** Deliberately claims the same id as AlphaPlugin. */
class BetaPlugin extends MyraPlugin
{
    public static function id(): string
    {
        return 'duplicated-id';
    }

    public function manifest(Manifest $manifest): Manifest
    {
        return $manifest->permissions(['beta' => ['view']]);
    }
}
