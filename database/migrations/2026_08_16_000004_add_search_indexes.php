<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive only. Driver-guarded: SQLite is used in tests. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $t) {
            $t->index('status', 'users_status_idx');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->index(['created_by', 'created_at'], 'users_owner_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('users_status_idx');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('users_owner_idx');
        });
    }
};
