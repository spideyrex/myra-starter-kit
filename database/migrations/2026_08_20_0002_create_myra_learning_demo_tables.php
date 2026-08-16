<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myra_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('myra_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('myra_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // The scopeBindings lookup path.
            $table->index(['course_id', 'position']);
            $table->index(['course_id', 'id']);
        });

        Schema::create('myra_site_identity', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('tagline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myra_site_identity');
        Schema::dropIfExists('myra_lessons');
        Schema::dropIfExists('myra_courses');
    }
};
