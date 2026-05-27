<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds two general settings:
 *   login_tagline        → editable copy shown on the login screen
 *   registration_enabled → server-enforced public sign-up toggle
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['group' => 'general', 'name' => 'login_tagline', 'payload' => json_encode('The best admin experience starts with a great foundation.')],
            ['group' => 'general', 'name' => 'registration_enabled', 'payload' => json_encode(true)],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'name' => $setting['name']],
                array_merge($setting, ['locked' => false, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'general')
            ->whereIn('name', ['login_tagline', 'registration_enabled'])
            ->delete();
    }
};
