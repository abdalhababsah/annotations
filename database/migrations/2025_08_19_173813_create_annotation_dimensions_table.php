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
        Schema::create('annotation_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('dimension_type', ['numeric_scale', 'categorical', 'boolean', 'text', 'repeatable_form']);
            $table->integer('scale_min')->nullable()->comment('For numeric scales');
            $table->integer('scale_max')->nullable()->comment('For numeric scales');
            $table->json('scale_labels')->nullable()->comment('JSON scale descriptions');
            $table->json('form_template')->nullable()->comment('JSON template for repeatable forms');
            $table->boolean('is_required')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annotation_dimensions');
    }
};
