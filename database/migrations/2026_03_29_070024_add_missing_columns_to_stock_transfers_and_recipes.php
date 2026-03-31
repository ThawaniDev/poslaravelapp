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
        if (!Schema::hasColumn('stock_transfers', 'updated_at')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }

        Schema::table('recipes', function (Blueprint $table) {
            if (!Schema::hasColumn('recipes', 'name')) {
                $table->string('name', 255)->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('recipes', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });
    }
};
