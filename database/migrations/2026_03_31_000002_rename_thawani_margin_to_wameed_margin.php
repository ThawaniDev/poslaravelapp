<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registers', 'thawani_margin_percentage')) {
            Schema::table('registers', function (Blueprint $table) {
                $table->renameColumn('thawani_margin_percentage', 'wameed_margin_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registers', 'wameed_margin_percentage')) {
            Schema::table('registers', function (Blueprint $table) {
                $table->renameColumn('wameed_margin_percentage', 'thawani_margin_percentage');
            });
        }
    }
};
