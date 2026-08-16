<?php

namespace App\Console\Commands\Brand;

use App\Brand\BrandManager;
use Illuminate\Console\Command;

class BrandFixtureCommand extends Command
{
    protected $signature = 'brand:fixture {--path=}';

    protected $description = 'Write tests/js/fixtures/brand-tokens.json from the real brand resolver';

    public function handle(BrandManager $brand): int
    {
        $path = (string) ($this->option('path') ?: base_path('tests/js/fixtures/brand-tokens.json'));

        $payload = [
            'brand' => $brand->toInertiaProp(),
            'light' => $brand->current()->cssVariables(false),
            'dark' => $brand->current()->cssVariables(true),
        ];

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}
