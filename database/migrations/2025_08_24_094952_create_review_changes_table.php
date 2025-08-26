<?php
// 010_create_review_changes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->onDelete('cascade');
            $table->foreignId('dimension_id')->constrained('annotation_dimensions')->onDelete('cascade');
            $table->string('original_value', 100)->nullable();
            $table->string('corrected_value', 100)->nullable();
            $table->integer('original_numeric')->nullable();
            $table->integer('corrected_numeric')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->index('review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_changes');
    }
};