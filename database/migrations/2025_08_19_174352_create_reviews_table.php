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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->decimal('overall_quality_score', 3, 2)->nullable();
            $table->text('detailed_feedback')->nullable();
            $table->enum('action_taken', ['approved', 'rejected', 'modified', 'returned_for_revision'])->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('annotation_id');
            $table->index(['reviewer_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
