<?php

namespace App\Console\Commands;

use App\Appearance\AppearanceManager;
use App\Brand\BrandManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The break-glass path: works when you are already locked out of the admin.
 *
 * Together with MYRA_AUTH_APPEARANCE=false this is the way back from any
 * surface an operator cannot undo through the UI.
 */
class AppearanceResetCommand extends Command
{
    protected $signature = 'myra:appearance {action=reset} {--surface=all}';

    protected $description = 'Reset the auth and/or page surface appearance to its shipped default';

    /** @var array<string,mixed> */
    public const DEFAULTS = [
        'auth_layout' => 'split',
        'auth_flip' => false,
        'auth_show_tagline' => true,
        'auth_bg_type' => 'brand',
        'auth_bg_color' => null,
        'auth_bg_recipe' => null,
        'auth_bg_image_path' => null,
        'auth_bg_scrim' => 'medium',
        'page_bg_type' => 'none',
        'page_bg_color' => null,
        'page_bg_recipe' => null,
        'page_bg_image_path' => null,
        'page_bg_scrim' => 'medium',
        'page_navbar_translucent' => false,
    ];

    public function handle(): int
    {
        if ($this->argument('action') !== 'reset') {
            $this->error('Unknown action. The only supported action is "reset".');

            return self::FAILURE;
        }

        $surface = (string) $this->option('surface');

        if (! in_array($surface, ['all', 'auth', 'page'], true)) {
            $this->error('--surface must be one of: all, auth, page.');

            return self::FAILURE;
        }

        $written = 0;

        foreach (self::DEFAULTS as $name => $value) {
            if ($surface !== 'all' && ! str_starts_with($name, $surface.'_')) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
            );

            $written++;
        }

        app(AppearanceManager::class)->forget();
        app(BrandManager::class)->forget();

        $this->info("Appearance reset ({$surface}): {$written} settings restored to their defaults.");

        return self::SUCCESS;
    }
}
