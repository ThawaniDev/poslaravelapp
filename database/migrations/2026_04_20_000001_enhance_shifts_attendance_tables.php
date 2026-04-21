<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance shift_templates (idempotent)
        if (!Schema::hasColumn('shift_templates', 'break_duration_minutes')) {
            Schema::table('shift_templates', function (Blueprint $table) {
                $table->integer('break_duration_minutes')->default(0)->after('end_time');
            });
        }
        if (!Schema::hasColumn('shift_templates', 'is_active')) {
            Schema::table('shift_templates', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('color');
            });
        }

        // Enhance shift_schedules (idempotent)
        if (!Schema::hasColumn('shift_schedules', 'notes')) {
            Schema::table('shift_schedules', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('swapped_with_id');
            });
        }

        // Convert single date to date range (start_date + end_date)
        if (!Schema::hasColumn('shift_schedules', 'start_date')) {
            DB::statement('ALTER TABLE shift_schedules ADD COLUMN start_date DATE');
        }
        if (!Schema::hasColumn('shift_schedules', 'end_date')) {
            DB::statement('ALTER TABLE shift_schedules ADD COLUMN end_date DATE');
        }
        if (Schema::hasColumn('shift_schedules', 'date')) {
            DB::statement('UPDATE shift_schedules SET start_date = date, end_date = date WHERE start_date IS NULL');
        }
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shift_schedules ALTER COLUMN start_date SET NOT NULL');
            DB::statement('ALTER TABLE shift_schedules ALTER COLUMN end_date SET NOT NULL');
            DB::statement('ALTER TABLE shift_schedules DROP CONSTRAINT IF EXISTS shift_schedules_staff_user_id_date_shift_template_id_unique');
            if (Schema::hasColumn('shift_schedules', 'date')) {
                DB::statement('ALTER TABLE shift_schedules DROP COLUMN date');
            }
            DB::statement('DROP INDEX IF EXISTS shift_schedules_staff_period_template_unique');
            DB::statement('CREATE UNIQUE INDEX shift_schedules_staff_period_template_unique ON shift_schedules (staff_user_id, start_date, end_date, shift_template_id)');
        }

        // Enhance attendance_records (idempotent)
        if (!Schema::hasColumn('attendance_records', 'status')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->string('status', 30)->nullable()->after('auth_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shift_templates', 'break_duration_minutes')) {
            Schema::table('shift_templates', function (Blueprint $table) {
                $table->dropColumn('break_duration_minutes');
            });
        }
        if (Schema::hasColumn('shift_templates', 'is_active')) {
            Schema::table('shift_templates', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn('shift_schedules', 'notes')) {
            Schema::table('shift_schedules', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }

        if (!Schema::hasColumn('shift_schedules', 'date')) {
            DB::statement('ALTER TABLE shift_schedules ADD COLUMN date DATE');
        }
        if (Schema::hasColumn('shift_schedules', 'start_date')) {
            DB::statement('UPDATE shift_schedules SET date = start_date WHERE date IS NULL');
        }
        DB::statement('ALTER TABLE shift_schedules ALTER COLUMN date SET NOT NULL');
        DB::statement('DROP INDEX IF EXISTS shift_schedules_staff_period_template_unique');
        if (Schema::hasColumn('shift_schedules', 'start_date')) {
            DB::statement('ALTER TABLE shift_schedules DROP COLUMN start_date');
        }
        if (Schema::hasColumn('shift_schedules', 'end_date')) {
            DB::statement('ALTER TABLE shift_schedules DROP COLUMN end_date');
        }
        DB::statement('CREATE UNIQUE INDEX shift_schedules_staff_user_id_date_shift_template_id_unique ON shift_schedules (staff_user_id, date, shift_template_id)');

        if (Schema::hasColumn('attendance_records', 'status')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
