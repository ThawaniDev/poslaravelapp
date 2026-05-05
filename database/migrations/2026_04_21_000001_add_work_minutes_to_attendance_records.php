<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_records', 'work_minutes')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->integer('work_minutes')->nullable()->after('break_minutes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_records', 'work_minutes')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn('work_minutes');
            });
        }
    }
};
