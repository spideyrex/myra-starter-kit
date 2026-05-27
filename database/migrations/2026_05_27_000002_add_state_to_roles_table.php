<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds super-admin-controlled state to roles:
 *   is_active → a disabled role cannot be assigned to users
 *   visible   → a hidden role is omitted from non-super-admin pickers/lists
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('guard_name');
            }
            if (! Schema::hasColumn('roles', 'visible')) {
                $table->boolean('visible')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'visible']);
        });
    }
};
