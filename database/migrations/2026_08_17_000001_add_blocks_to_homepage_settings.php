<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strictly additive: one new homepage setting, no column touched, no row dropped.
 *
 * It seeds an EMPTY list on purpose. An empty list means the flat singleton
 * settings still drive the page, so the upgrade is a no-op for the public page
 * AND the existing homepage editor stays the authoritative one. Adopting the
 * block model is an explicit act in the page builder (which offers the same
 * LegacyHomepageBlocks conversion for review), never something an upgrade does
 * behind the author's back and leaves them with an editor that changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('group', 'homepage')
            ->where('name', 'blocks')
            ->exists();

        if ($exists) {
            return; // never clobber an authored page on a re-run
        }

        DB::table('settings')->insert([
            'group' => 'homepage',
            'name' => 'blocks',
            'payload' => json_encode([]),
            'locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'homepage')
            ->where('name', 'blocks')
            ->delete();
    }
};
