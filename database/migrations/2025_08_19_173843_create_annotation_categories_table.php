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
        Schema::create('annotation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('annotation_dimensions')->onDelete('cascade');
            $table->string('category_name', 100);
            $table->string('category_value', 50)->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['dimension_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annotation_categories');
    }
};
