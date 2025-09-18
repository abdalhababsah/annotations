<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('task_custom_labels', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
        });

        // Backfill for existing rows
        $labels = DB::table('task_custom_labels')->whereNull('uuid')->get(['id']);
        foreach ($labels as $row) {
            DB::table('task_custom_labels')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('task_custom_labels', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_custom_labels', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
