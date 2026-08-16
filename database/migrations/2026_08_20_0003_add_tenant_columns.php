<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: a nullable team_id plus two composite indexes per table. No
 * backfill, no $fillable change, no scope. With myra.tenancy.enabled false the
 * column is never read, so this migration cannot change what any query returns.
 */
return new class extends Migration
{
    private const TABLES = ['articles', 'pages', 'categories', 'media', 'email_templates'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'team_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreignId('team_id')->nullable()->after('id')->constrained('teams')->nullOnDelete();
                $t->index(['team_id', 'created_at'], "{$table}_tenant_created_idx");
                $t->index(['team_id', 'id'], "{$table}_tenant_id_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'team_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_tenant_created_idx");
                $t->dropIndex("{$table}_tenant_id_idx");
                $t->dropConstrainedForeignId('team_id');
            });
        }
    }
};
