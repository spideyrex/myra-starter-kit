<?php

use App\Homepage\Sections\LegacyHomepageBlocks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strictly additive: one new homepage setting, no column touched, no row dropped.
 *
 * Reads RAW rows rather than app(HomepageSettings::class) — hydration would
 * fail on the very property this migration is adding.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('settings')
            ->where('group', 'homepage')
            ->pluck('payload', 'name')
            ->map(fn ($payload) => json_decode((string) $payload, true))
            ->all();

        DB::table('settings')->updateOrInsert(
            ['group' => 'homepage', 'name' => 'blocks'],
            [
                'payload' => json_encode(LegacyHomepageBlocks::fromRows($rows)),
                'locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'homepage')
            ->where('name', 'blocks')
            ->delete();
    }
};
