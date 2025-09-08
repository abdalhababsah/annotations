<?php
// 3. Create project_segmentation_labels table (many-to-many relationship)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_segmentation_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('label_id')->constrained('segmentation_labels')->onDelete('cascade');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'label_id']);
            $table->index(['project_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_segmentation_labels');
    }
};