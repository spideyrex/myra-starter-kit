<?php

namespace App\Console\Commands\Brand;

use App\Brand\BrandManager;
use Illuminate\Console\Command;

class BrandClearCommand extends Command
{
    protected $signature = 'brand:clear';

    protected $description = 'Forget the resolved brand tokens, version probe and shared settings cache';

    public function handle(BrandManager $brand): int
    {
        $brand->forget();

        $this->info('Brand cache cleared.');

        return self::SUCCESS;
    }
}
