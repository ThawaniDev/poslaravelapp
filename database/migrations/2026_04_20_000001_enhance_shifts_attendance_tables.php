<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            // Convert single date to date range (start_date + end_date)
            $table->date('start_date')->after('shift_template_id')->nullable();
            $table->date('end_date')->after('start_date')->nullable();
        });

        // Copy existing date values into start_date and end_date
        DB::statement('UPDATE shift_schedules SET start_date = date, end_date = date WHERE start_date IS NULL');

        // Now make them not-null and drop the old date column + old unique
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->dropUnique(['staff_user_id', 'date', 'shift_template_id']);
            $table->dropColumn('date');
            $table->unique(['staff_user_id', 'start_date', 'end_date', 'shift_template_id'], 'shift_schedules_staff_period_template_unique');
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
            $table->date('date')->after('shift_template_id')->nullable();
        });

        DB::statement('UPDATE shift_schedules SET date = start_date WHERE date IS NULL');

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
            $table->dropUnique('shift_schedules_staff_period_template_unique');
            $table->dropColumn(['start_date', 'end_date', 'notes']);
            $table->unique(['staff_user_id', 'date', 'shift_template_id']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
