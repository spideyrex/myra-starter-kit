<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Higher number wins. Default 0 makes an unmigrated install a total tie, broken by roles.id ASC. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'priority')) {
                $table->unsignedSmallInteger('priority')->default(0)->after('visible');
                $table->index(['priority', 'id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['priority', 'id']);
            $table->dropColumn('priority');
        });
    }
};
