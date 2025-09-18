<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum to add the new 'expired' value
        DB::statement("ALTER TABLE skip_activities MODIFY COLUMN skip_reason ENUM('technical_issue', 'unclear_audio', 'unclear_annotation', 'personal_reason', 'expired', 'other')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 'expired' option from the enum
        DB::statement("ALTER TABLE skip_activities MODIFY COLUMN skip_reason ENUM('technical_issue', 'unclear_audio', 'unclear_annotation', 'personal_reason', 'other')");
    }
};