<?php
// 4. Create task_custom_labels table (task-specific labels created by annotators)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_custom_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('name', 100)->comment('Custom label name created by annotator');
            $table->string('color', 7)->default('#6B7280')->comment('Default gray color for custom labels');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->comment('Annotator or reviewer who created this label');
            $table->timestamps();

            $table->index(['task_id', 'created_by']);
            $table->unique(['task_id', 'name'], 'task_custom_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_custom_labels');
    }
};