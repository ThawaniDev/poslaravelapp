<?php

use App\Domain\WameedAI\Models\AIBillingSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->decimal('billed_cost_usd', 10, 6)->default(0)->after('estimated_cost_usd');
            $table->decimal('margin_percentage_applied', 5, 3)->nullable()->after('billed_cost_usd');
        });

        // Backfill existing logs with the current global margin
        $margin = 20.0;
        try {
            $setting = DB::table('ai_billing_settings')->where('key', 'margin_percentage')->value('value');
            if ($setting !== null) {
                $margin = (float) $setting;
            }
        } catch (\Throwable $e) {
            // Table might not exist yet, use default
        }

        DB::table('ai_usage_logs')
            ->where('billed_cost_usd', 0)
            ->where('estimated_cost_usd', '>', 0)
            ->update([
                'margin_percentage_applied' => $margin,
                'billed_cost_usd' => DB::raw("ROUND(estimated_cost_usd * (1 + {$margin} / 100), 6)"),
            ]);
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['billed_cost_usd', 'margin_percentage_applied']);
        });
    }
};
