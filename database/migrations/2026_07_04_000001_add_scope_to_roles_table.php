<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'scope')) {
                $table->string('scope', 20)->default('branch')->after('is_predefined')
                      ->comment('organization = cross-branch access, branch = single branch only');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
