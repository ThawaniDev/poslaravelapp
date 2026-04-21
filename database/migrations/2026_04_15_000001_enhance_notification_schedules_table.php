<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_schedules')) {
            return;
        }
        Schema::table('notification_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_schedules', 'category')) {
                $table->string('category', 30)->nullable();
            }
            if (!Schema::hasColumn('notification_schedules', 'title')) {
                $table->string('title', 255)->nullable();
            }
            if (!Schema::hasColumn('notification_schedules', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('notification_schedules', 'priority')) {
                $table->string('priority', 10)->default('normal');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_schedules')) {
            return;
        }
        Schema::table('notification_schedules', function (Blueprint $table) {
            foreach (['category', 'title', 'message', 'priority'] as $col) {
                if (Schema::hasColumn('notification_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
