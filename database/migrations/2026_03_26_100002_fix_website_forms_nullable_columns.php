<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_hardware_quotes', function (Blueprint $table) {
            $table->string('business_name')->nullable()->change();
        });

        Schema::table('website_consultation_requests', function (Blueprint $table) {
            $table->string('business_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('website_hardware_quotes', function (Blueprint $table) {
            $table->string('business_name')->nullable(false)->change();
        });

        Schema::table('website_consultation_requests', function (Blueprint $table) {
            $table->string('business_name')->nullable(false)->change();
        });
    }
};
