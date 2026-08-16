<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: composite (created_at, id) indexes on existing tables — the
 * access path a stable sort or a cursor walk needs — plus one new demo table.
 * No column is added, changed or dropped; no row is touched.
 */
return new class extends Migration
{
    private const INDEXES = [
        'articles' => 'articles_created_id_idx',
        'pages' => 'pages_created_id_idx',
        'users' => 'users_created_id_idx',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $name) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($this->hasIndex($table, $name)) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->index(['created_at', 'id'], $name));
        }

        if (! Schema::hasTable('myra_scale_rows')) {
            Schema::create('myra_scale_rows', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('email');
                $t->string('status', 20);
                $t->unsignedInteger('amount');
                $t->timestamps();
                $t->index(['created_at', 'id'], 'scale_created_id_idx');
                $t->index(['status', 'created_at', 'id'], 'scale_status_created_idx');
                $t->index(['name', 'id'], 'scale_name_id_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('myra_scale_rows');

        foreach (self::INDEXES as $table => $name) {
            if (! Schema::hasTable($table) || ! $this->hasIndex($table, $name)) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strtolower((string) $index['name']) === strtolower($name)) {
                return true;
            }
        }

        return false;
    }
};
