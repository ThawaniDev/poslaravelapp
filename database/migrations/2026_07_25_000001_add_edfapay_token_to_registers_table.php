<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registers', 'edfapay_token')) {
            return;
        }

        Schema::table('registers', function (Blueprint $table) {
            $table->text('edfapay_token')
                  ->nullable()
                  ->after('nearpay_auth_key')
                  ->comment('EdfaPay SoftPOS terminal token for SDK silent initialization');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('edfapay_token');
        });
    }
};
