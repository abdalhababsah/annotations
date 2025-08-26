<?php
// 008_create_annotation_values_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotation_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            $table->foreignId('dimension_id')->constrained('annotation_dimensions')->onDelete('cascade');
            $table->string('selected_value', 100)->nullable()->comment('Selected value for dimension');
            $table->integer('numeric_value')->nullable()->comment('For numeric scale dimensions');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['annotation_id', 'dimension_id']);
            $table->index('dimension_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotation_values');
    }
};