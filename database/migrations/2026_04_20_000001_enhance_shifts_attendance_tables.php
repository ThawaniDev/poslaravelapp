<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance shift_templates
        Schema::table('shift_templates', function (Blueprint $table) {
            $table->integer('break_duration_minutes')->default(0)->after('end_time');
            $table->boolean('is_active')->default(true)->after('color');
        });

        // Enhance shift_schedules
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('swapped_with_id');
        });

        // Enhance attendance_records
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status', 30)->nullable()->after('auth_method');
            // status: on_time, late, early_departure, absent
        });
    }

    public function down(): void
    {
        Schema::table('shift_templates', function (Blueprint $table) {
            $table->dropColumn(['break_duration_minutes', 'is_active']);
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
