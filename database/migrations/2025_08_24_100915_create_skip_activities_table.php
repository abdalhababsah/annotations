<?php
// 011_create_skip_activities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skip_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('activity_type', ['task', 'review'])->comment('What was skipped');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->comment('Task that was skipped');
            $table->foreignId('annotation_id')->nullable()->constrained('annotations')->comment('For review skips');
            $table->enum('skip_reason', ['technical_issue', 'unclear_audio', 'unclear_annotation', 'personal_reason', 'other']);
            $table->text('skip_description')->nullable();
            $table->timestamp('skipped_at')->useCurrent();
            $table->timestamps();

            $table->index(['project_id', 'user_id']);
            $table->index(['activity_type', 'skipped_at']);
            $table->index(['user_id', 'task_id']);
            $table->unique(['user_id', 'task_id', 'activity_type'], 'user_task_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skip_activities');
    }
};