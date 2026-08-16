<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A layout is a PRIVATE preference, not a shareable document: no name, no
 * visibility, no is_default, and therefore no cross-user read path at all.
 * Named/team layouts are deferred to v2.6 and would take a second table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dashboard_key', 120);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['user_id', 'dashboard_key']);
            $table->index(['dashboard_key', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }
};
