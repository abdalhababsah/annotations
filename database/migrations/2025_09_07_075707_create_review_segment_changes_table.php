<?php
// 6. Create review_segment_changes table (for tracking reviewer changes to segments)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_segment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->onDelete('cascade');
            $table->foreignId('segment_id')->constrained('task_segments')->onDelete('cascade');
            $table->enum('change_type', ['modified', 'deleted', 'added'])->comment('Type of change made by reviewer');
            
            // Original values (for modified/deleted segments)
            $table->decimal('original_start_time', 10, 3)->nullable();
            $table->decimal('original_end_time', 10, 3)->nullable();
            $table->foreignId('original_project_label_id')->nullable()->constrained('segmentation_labels');
            $table->foreignId('original_custom_label_id')->nullable()->constrained('task_custom_labels');
            
            // New values (for modified/added segments)
            $table->decimal('new_start_time', 10, 3)->nullable();
            $table->decimal('new_end_time', 10, 3)->nullable();
            $table->foreignId('new_project_label_id')->nullable()->constrained('segmentation_labels');
            $table->foreignId('new_custom_label_id')->nullable()->constrained('task_custom_labels');
            
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->index('review_id');
            $table->index(['segment_id', 'change_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_segment_changes');
    }
};