<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['group' => 'ai', 'name' => 'enabled', 'payload' => json_encode(false)],
            ['group' => 'ai', 'name' => 'provider', 'payload' => json_encode('openai')],
            ['group' => 'ai', 'name' => 'api_key', 'payload' => json_encode(null)],
            ['group' => 'ai', 'name' => 'model', 'payload' => json_encode(null)],
            ['group' => 'ai', 'name' => 'base_url', 'payload' => json_encode(null)],
            ['group' => 'ai', 'name' => 'temperature', 'payload' => json_encode(0.7)],
            ['group' => 'ai', 'name' => 'max_tokens', 'payload' => json_encode(2048)],
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
        DB::table('settings')->where('group', 'ai')->delete();
    }
};
