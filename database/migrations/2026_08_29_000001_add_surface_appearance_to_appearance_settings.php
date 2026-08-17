<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The fourteen v2.8 rows and their shipped defaults. */
    private const DEFAULTS = [
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

    public function up(): void
    {
        foreach (self::DEFAULTS as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'appearance')
            ->whereIn('name', array_keys(self::DEFAULTS))
            ->delete();
    }
};
