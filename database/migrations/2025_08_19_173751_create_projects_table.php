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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('project_type', ['audio', 'image']);
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'archived'])->default('draft');
            $table->foreignId('owner_id')->constrained('users');
            $table->enum('ownership_type', ['self_created', 'admin_assigned'])->default('self_created');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->comment('Admin who assigned project');
            $table->decimal('quality_threshold', 3, 2)->default(0.80);
            $table->text('annotation_guidelines')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index('created_by');
            $table->index('ownership_type');
            $table->index('project_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
