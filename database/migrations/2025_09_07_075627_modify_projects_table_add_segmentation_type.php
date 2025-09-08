<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('project_type', ['annotation', 'segmentation'])->default('annotation')->after('status');
            $table->boolean('allow_custom_labels')->default(false)->after('project_type')->comment('Allow annotators to add new labels during task execution');
        });
    }
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'allow_custom_labels']);
        });
    }
};
