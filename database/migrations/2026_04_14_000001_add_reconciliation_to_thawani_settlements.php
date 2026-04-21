<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thawani_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('thawani_settlements', 'reconciled')) {
                $table->boolean('reconciled')->default(false)->after('thawani_reference');
            }
            if (!Schema::hasColumn('thawani_settlements', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable()->after('reconciled');
            }
            if (!Schema::hasColumn('thawani_settlements', 'reconciled_by')) {
                $table->uuid('reconciled_by')->nullable()->after('reconciled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('thawani_settlements', function (Blueprint $table) {
            $table->dropColumn(['reconciled', 'reconciled_at', 'reconciled_by']);
        });
    }
};
