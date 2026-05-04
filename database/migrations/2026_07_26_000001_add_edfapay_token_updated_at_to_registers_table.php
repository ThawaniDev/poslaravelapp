<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registers', 'edfapay_token_updated_at')) {
            return;
        }

        Schema::table('registers', function (Blueprint $table) {
            $table->timestamp('edfapay_token_updated_at')
                  ->nullable()
                  ->after('edfapay_token')
                  ->comment('Timestamp of when the EdfaPay token was last provisioned or updated');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('edfapay_token_updated_at');
        });
    }
};
