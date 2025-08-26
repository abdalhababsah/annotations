<?php
// 004_create_dimension_values_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('annotation_dimensions')->onDelete('cascade');
            $table->string('value', 100)->comment('e.g., male, female, other');
            $table->string('label', 100)->nullable()->comment('Display label');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['dimension_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dimension_values');
    }
};
