<?php
// 009_create_reviews_table.php - MODIFIED (removed skip fields)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->enum('action', ['approved', 'rejected'])->nullable();
            $table->integer('feedback_rating')->nullable()->comment('Rating from 1-5');
            $table->text('feedback_comment')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('Review expiration time');
            $table->integer('review_time_spent')->nullable()->comment('Seconds spent on review');
            $table->timestamps();

            $table->index('annotation_id');
            $table->index(['reviewer_id', 'completed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

