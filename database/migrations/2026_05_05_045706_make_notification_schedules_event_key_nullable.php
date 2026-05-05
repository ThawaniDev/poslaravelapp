<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make event_key nullable on notification_schedules.
 *
 * Message-based (direct title/body) schedules do not require an event_key.
 * Previously this was NOT NULL, which caused a constraint violation when
 * creating schedules without a template event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->string('event_key', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->string('event_key', 50)->nullable(false)->change();
        });
    }
};
