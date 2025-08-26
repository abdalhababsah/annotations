<?php
// 003_create_annotation_dimensions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotation_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name', 100)->comment('e.g., Gender, Emotion, Age');
            $table->text('description')->nullable();
            $table->enum('dimension_type', ['categorical', 'numeric_scale'])->default('categorical');
            $table->integer('scale_min')->nullable()->comment('For numeric scales (e.g., 1)');
            $table->integer('scale_max')->nullable()->comment('For numeric scales (e.g., 5)');
            $table->boolean('is_required')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotation_dimensions');
    }
};
