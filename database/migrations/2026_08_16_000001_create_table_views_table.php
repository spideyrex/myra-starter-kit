<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_key', 120);
            $table->string('name', 60);
            $table->string('slug', 26)->unique();
            $table->string('visibility', 16)->default('private');
            $table->boolean('is_default')->default(false);
            $table->json('payload');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'table_key', 'name']);
            $table->index(['table_key', 'user_id']);
            $table->index(['table_key', 'team_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_views');
    }
};
