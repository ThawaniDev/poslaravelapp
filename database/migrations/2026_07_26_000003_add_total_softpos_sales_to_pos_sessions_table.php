<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pos_sessions', 'total_softpos_sales')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->decimal('total_softpos_sales', 15, 2)
                  ->default(0)
                  ->after('total_card_sales')
                  ->comment('Running total of SoftPOS (EdfaPay tap-to-pay) sales in this session');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn('total_softpos_sales');
        });
    }
};
