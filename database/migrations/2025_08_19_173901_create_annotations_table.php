<?php
// 007_create_annotations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('annotator_id')->constrained('users');
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->integer('total_time_spent')->nullable()->comment('Seconds');
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['annotator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};