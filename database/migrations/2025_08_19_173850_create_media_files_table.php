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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('original_filename', 500);
            $table->string('stored_filename', 500);
            $table->string('file_path', 1000);
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->enum('media_type', ['audio', 'image']);
            $table->decimal('duration', 10, 2)->nullable()->comment('For audio in seconds');
            $table->string('dimensions', 20)->nullable()->comment('For images: "1920x1080"');
            $table->json('metadata')->nullable()->comment('Technical metadata in JSON');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('project_id');
            $table->index('media_type');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
