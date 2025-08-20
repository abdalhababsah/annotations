<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('media_file_id')->constrained('media_files')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('task_name', 255)->nullable();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->integer('estimated_duration')->nullable()->comment('Minutes');
            $table->integer('actual_duration')->nullable()->comment('Minutes');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('media_file_id');
            $table->index('due_date');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
