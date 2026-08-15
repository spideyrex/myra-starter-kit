<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_key', 120);
            $table->string('name', 60);
            $table->string('slug', 26)->unique();
            $table->json('state');
            $table->string('format', 8)->default('pdf');
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('hour')->default(8);
            $table->unsignedTinyInteger('minute')->default(0);
            $table->string('timezone', 64)->default('UTC');
            $table->json('recipients');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->boolean('skip_if_empty')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamps();

            // Precomputed next_run_at makes dispatch an index range scan, not a
            // cron-expression evaluation across every row.
            $table->index(['is_active', 'next_run_at']);
            $table->index(['report_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
