<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'appearance', 'name' => 'logo_position'],
            ['payload' => json_encode('header'), 'locked' => false, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'appearance')->where('name', 'logo_position')->delete();
    }
};
