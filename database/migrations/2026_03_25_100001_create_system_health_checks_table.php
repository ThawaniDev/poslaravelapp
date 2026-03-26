<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_health_checks')) {
            return;
        }

        Schema::create('system_health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('service', 100)->index();
            $table->string('status', 20)->default('unknown')->index();
            $table->integer('response_time_ms')->default(0);
            $table->jsonb('details')->nullable();
            $table->timestamp('checked_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};
