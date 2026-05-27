<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fields = ['sidebar_background', 'sidebar_foreground', 'sidebar_accent'];

        foreach ($fields as $field) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $field],
                ['payload' => json_encode(null), 'locked' => false, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'appearance')
            ->whereIn('name', ['sidebar_background', 'sidebar_foreground', 'sidebar_accent'])
            ->delete();
    }
};
