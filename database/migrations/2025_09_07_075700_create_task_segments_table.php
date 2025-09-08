<?php
// 5. Create task_segments table (stores the actual segmentation data)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            
            // Either project label or custom label (one must be null, one must have value)
            $table->foreignId('project_label_id')->nullable()->constrained('segmentation_labels')->onDelete('cascade');
            $table->foreignId('custom_label_id')->nullable()->constrained('task_custom_labels')->onDelete('cascade');
            
            $table->decimal('start_time', 10, 3)->comment('Start time in seconds with millisecond precision');
            $table->decimal('end_time', 10, 3)->comment('End time in seconds with millisecond precision');
            $table->decimal('duration', 10, 3)->storedAs('end_time - start_time')->comment('Calculated duration');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'start_time']);
            $table->index(['annotation_id']);
            $table->index(['project_label_id']);
            $table->index(['custom_label_id']);
            
            // Ensure either project_label_id OR custom_label_id is set, not both
            // $table->check('(project_label_id IS NOT NULL AND custom_label_id IS NULL) OR (project_label_id IS NULL AND custom_label_id IS NOT NULL)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_segments');
    }
};