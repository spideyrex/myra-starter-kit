<?php

namespace Tests\Feature\Plugin\Fixtures;

use App\Admin\Plugin\Manifest;
use App\Admin\Plugin\MyraPlugin;

/** Requires a Myra release that does not exist yet. */
class FuturePlugin extends MyraPlugin
{
    public static function id(): string
    {
        return 'from-the-future';
    }

    public function manifest(Manifest $manifest): Manifest
    {
        return $manifest->requires('99.0.0');
    }
}
