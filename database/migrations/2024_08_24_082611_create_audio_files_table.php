<?php
// 005_create_audio_files_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('original_filename', 500);
            $table->string('stored_filename', 500);
            $table->string('file_path', 1000);
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->decimal('duration', 10, 2)->nullable()->comment('Duration in seconds');
            $table->json('metadata')->nullable()->comment('Audio metadata');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('project_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_files');
    }
};