<?php
// 006_create_tasks_table.php - MODIFIED (removed skip fields)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('audio_file_id')->constrained('audio_files')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('Task expiration time');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
