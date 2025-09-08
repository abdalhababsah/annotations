<?php
// 2. Create segmentation_labels table (global label pool)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('segmentation_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('e.g., Speaker A, Music, Silence');
            $table->string('color', 7)->default('#3B82F6')->comment('Hex color for UI display');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segmentation_labels');
    }
};