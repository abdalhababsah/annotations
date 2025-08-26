<?php
// 001_create_projects_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'archived'])->default('draft');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->integer('task_time_minutes')->default(30)->comment('Time limit per task in minutes');
            $table->integer('review_time_minutes')->default(15)->comment('Time limit per review in minutes');
            $table->text('annotation_guidelines')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
