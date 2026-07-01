<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make registers.device_id nullable.
 *
 * The device_id is auto-assigned by the POS Flutter app when a cashier opens
 * their first shift on a physical device. Registers created via the admin panel
 * therefore legitimately have no device_id yet — enforcing NOT NULL prevents
 * creating any register from the admin UI and causes a 500 on the create page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->string('device_id', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->string('device_id', 255)->nullable(false)->change();
        });
    }
};
