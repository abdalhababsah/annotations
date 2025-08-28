<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'in_progress', 'completed', 'paused'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->integer('total_tasks')->default(0);
            $table->integer('completed_tasks')->default(0);
            $table->integer('approved_tasks')->default(0);
            $table->integer('rejected_tasks')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('batches');
    }
};