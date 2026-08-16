<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-scoped dashboard defaults. Kept OUT of dashboard_layouts on purpose:
 * that table's whole safety story is "every row is owned by exactly one user
 * and scopeForUser is the only read path".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_dashboards')) {
            return;
        }

        Schema::create('role_dashboards', function (Blueprint $table) {
            $table->id();
            // Nothing inherits Spatie's cascade; without this, deleting a role orphans the row.
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('dashboard_key', 120);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['role_id', 'dashboard_key']);
            $table->index('dashboard_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_dashboards');
    }
};
