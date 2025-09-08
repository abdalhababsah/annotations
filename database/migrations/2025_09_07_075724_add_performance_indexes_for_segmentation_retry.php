<?php
// 8. Add indexes for better performance on segmentation queries
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status', 'assigned_to'], 'tasks_project_status_user_index');
        });

        Schema::table('annotations', function (Blueprint $table) {
            $table->index(['task_id', 'annotator_id', 'status'], 'annotations_task_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_project_status_user_index');
        });

        Schema::table('annotations', function (Blueprint $table) {
            $table->dropIndex('annotations_task_user_status_index');
        });
    }
};