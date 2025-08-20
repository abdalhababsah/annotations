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
        Schema::create('quality_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('metric_type', 50);
            $table->decimal('metric_value', 10, 4);
            $table->string('calculation_period', 20)->nullable()->comment('daily, weekly, monthly');
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->index(['project_id', 'user_id', 'metric_type']);
            $table->index('calculated_at');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_metrics');
    }
};
