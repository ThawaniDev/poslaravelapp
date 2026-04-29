<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zatca_certificates', function (Blueprint $table) {
            // Stores which ZATCA environment the cert was enrolled in.
            // Values: 'developer-portal' | 'simulation' | 'production'
            // This lets multiple stores run on different environments
            // simultaneously without a global .env switch.
            $table->string('environment', 30)->default('developer-portal')->after('secret');
            // The exact API base URL used at enroll time — lets us call
            // renewal/submission back against the same endpoint.
            $table->string('api_url', 255)->nullable()->after('environment');
        });

        // Back-fill existing rows from current .env so nothing breaks.
        $env = config('zatca.environment', 'developer-portal');
        $url = config('zatca.api_url');
        \Illuminate\Support\Facades\DB::table('zatca_certificates')
            ->update(['environment' => $env, 'api_url' => $url]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zatca_certificates', function (Blueprint $table) {
            $table->dropColumn(['environment', 'api_url']);
        });
    }
};
