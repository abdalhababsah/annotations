<?php
// 002_create_project_members_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('role', ['annotator', 'reviewer', 'project_admin']);
            $table->foreignId('assigned_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->integer('workload_limit')->nullable()->comment('Max concurrent tasks');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};