<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = ['template', 'section_order', 'template_options'];

    public function up(): void
    {
        $defaults = [
            'template' => 'classic',
            'section_order' => ['hero', 'features', 'testimonials', 'pricing', 'cta'],
            'template_options' => (object) [],
        ];

        foreach ($defaults as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'homepage', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'homepage')
            ->whereIn('name', self::NAMES)
            ->delete();
    }
};
