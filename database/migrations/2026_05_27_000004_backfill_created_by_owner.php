<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the owner column (created_by) on legacy rows so existing content
 * isn't orphaned once per-user data isolation is active. Rows with a null
 * created_by are assigned to the first super-admin (falling back to the first
 * user). Super-admins see everything regardless, so this only gives legacy
 * data a valid owner; it does not expose it to non-super-admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superRoleId = DB::table('roles')->where('name', 'super-admin')->value('id');

        $ownerId = $superRoleId
            ? DB::table('model_has_roles')->where('role_id', $superRoleId)->orderBy('model_id')->value('model_id')
            : null;

        $ownerId ??= DB::table('users')->orderBy('id')->value('id');

        if (! $ownerId) {
            return; // no users yet — nothing to backfill
        }

        foreach (['articles', 'pages', 'categories', 'email_templates', 'users'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_by')) {
                DB::table($table)->whereNull('created_by')->update(['created_by' => $ownerId]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill is not reversible.
    }
};
