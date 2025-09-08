
<?php
// 7. Modify annotation_dimensions table to handle segmentation projects
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('annotation_dimensions', function (Blueprint $table) {
            $table->enum('dimension_type', ['categorical', 'numeric_scale', 'segmentation'])->default('categorical')->change();
        });
    }

    public function down(): void
    {
        Schema::table('annotation_dimensions', function (Blueprint $table) {
            $table->enum('dimension_type', ['categorical', 'numeric_scale'])->default('categorical')->change();
        });
    }
};