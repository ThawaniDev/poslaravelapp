<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_print_history', function (Blueprint $table) {
            // Which command language was used: zpl | tspl | escpos | image
            $table->string('printer_language', 10)->nullable()->after('printer_name');
            // Total pages/batches if a large job was split
            $table->unsignedSmallInteger('job_pages')->nullable()->after('printer_language');
            // Duration of the print job in milliseconds (for performance monitoring)
            $table->unsignedInteger('duration_ms')->nullable()->after('job_pages');
        });
    }

    public function down(): void
    {
        Schema::table('label_print_history', function (Blueprint $table) {
            $table->dropColumn(['printer_language', 'job_pages', 'duration_ms']);
        });
    }
};
