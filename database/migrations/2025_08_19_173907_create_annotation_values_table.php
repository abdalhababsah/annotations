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
        Schema::create('annotation_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            $table->foreignId('dimension_id')->constrained('annotation_dimensions')->onDelete('cascade');
            $table->decimal('value_numeric', 10, 4)->nullable()->comment('For numeric ratings');
            $table->text('value_text')->nullable()->comment('For text input');
            $table->boolean('value_boolean')->nullable()->comment('For yes/no');
            $table->string('value_categorical', 100)->nullable()->comment('For category selection');
            $table->json('value_form_data')->nullable()->comment('JSON for repeatable forms - audio segments or image objects');
            $table->decimal('confidence_score', 3, 2)->nullable()->comment('Annotator confidence 0.0-1.0');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['annotation_id', 'dimension_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annotation_values');
    }
};
