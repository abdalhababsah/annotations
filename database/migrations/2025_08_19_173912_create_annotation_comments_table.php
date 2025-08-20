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
        Schema::create('annotation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained('annotations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->text('comment_text');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index('annotation_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annotation_comments');
    }
};
