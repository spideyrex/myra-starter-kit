<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an owner column (created_by) to models that gain per-user data
 * isolation. Articles and pages already have created_by. Existing rows keep
 * created_by = null and remain visible only to super-admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'users', 'email_templates'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['categories', 'users', 'email_templates'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('created_by');
                });
            }
        }
    }
};
