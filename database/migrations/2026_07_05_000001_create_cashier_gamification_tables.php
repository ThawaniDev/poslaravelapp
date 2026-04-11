<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        $uuidDefault = $driver === 'pgsql' ? 'gen_random_uuid()' : "('')";
        $jsonType = $driver === 'pgsql' ? 'JSONB' : 'JSON';
        $nowDefault = $driver === 'pgsql' ? 'NOW()' : 'CURRENT_TIMESTAMP';

        // ─── Cashier Performance Snapshots ───────────────────────────────
        // Aggregated metrics per cashier per period (daily/shift)
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_performance_snapshots (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                cashier_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                pos_session_id UUID REFERENCES pos_sessions(id) ON DELETE SET NULL,
                period_type VARCHAR(20) NOT NULL DEFAULT 'daily',

                -- Core Metrics
                date DATE NOT NULL,
                shift_start TIMESTAMP,
                shift_end TIMESTAMP,
                active_minutes INTEGER NOT NULL DEFAULT 0,

                -- Transaction Metrics
                total_transactions INTEGER NOT NULL DEFAULT 0,
                total_items_sold INTEGER NOT NULL DEFAULT 0,
                total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_discount_given DECIMAL(12,2) NOT NULL DEFAULT 0,
                avg_basket_size DECIMAL(12,2) NOT NULL DEFAULT 0,

                -- Speed Metrics
                items_per_minute DECIMAL(8,2) NOT NULL DEFAULT 0,
                avg_transaction_time_seconds INTEGER NOT NULL DEFAULT 0,

                -- Quality Metrics
                void_count INTEGER NOT NULL DEFAULT 0,
                void_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                void_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
                return_count INTEGER NOT NULL DEFAULT 0,
                return_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_count INTEGER NOT NULL DEFAULT 0,
                discount_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
                price_override_count INTEGER NOT NULL DEFAULT 0,
                no_sale_count INTEGER NOT NULL DEFAULT 0,
                upsell_count INTEGER NOT NULL DEFAULT 0,
                upsell_rate DECIMAL(5,4) NOT NULL DEFAULT 0,

                -- Cash Handling
                cash_variance DECIMAL(12,2) NOT NULL DEFAULT 0,
                cash_variance_absolute DECIMAL(12,2) NOT NULL DEFAULT 0,

                -- Anomaly
                risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                anomaly_flags {$jsonType} DEFAULT NULL,

                created_at TIMESTAMP DEFAULT {$nowDefault},
                updated_at TIMESTAMP DEFAULT {$nowDefault}
            )
        ");

        // Unique per cashier per day per period type (one daily snapshot per cashier)
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_cashier_perf_daily
            ON cashier_performance_snapshots (store_id, cashier_id, date, period_type, COALESCE(pos_session_id, '00000000-0000-0000-0000-000000000000'))
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_cashier_perf_store_date
            ON cashier_performance_snapshots (store_id, date)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_cashier_perf_risk
            ON cashier_performance_snapshots (store_id, risk_score DESC)
        ");

        // ─── Cashier Badges ─────────────────────────────────────────────
        // Badge definitions (store-scoped)
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_badges (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                slug VARCHAR(50) NOT NULL,
                name_en VARCHAR(100) NOT NULL,
                name_ar VARCHAR(100) NOT NULL,
                description_en VARCHAR(500),
                description_ar VARCHAR(500),
                icon VARCHAR(50) NOT NULL DEFAULT 'emoji_events',
                color VARCHAR(20) NOT NULL DEFAULT '#FD8209',
                trigger_type VARCHAR(50) NOT NULL,
                trigger_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
                period VARCHAR(20) NOT NULL DEFAULT 'daily',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT {$nowDefault},
                updated_at TIMESTAMP DEFAULT {$nowDefault},
                UNIQUE(store_id, slug)
            )
        ");

        // ─── Cashier Badge Awards ───────────────────────────────────────
        // Tracks which badges each cashier has earned
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_badge_awards (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                cashier_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                badge_id UUID NOT NULL REFERENCES cashier_badges(id) ON DELETE CASCADE,
                snapshot_id UUID REFERENCES cashier_performance_snapshots(id) ON DELETE SET NULL,
                earned_date DATE NOT NULL,
                period VARCHAR(20) NOT NULL DEFAULT 'daily',
                metric_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT {$nowDefault}
            )
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_badge_awards_cashier
            ON cashier_badge_awards (store_id, cashier_id, earned_date DESC)
        ");

        // ─── Cashier Anomalies ──────────────────────────────────────────
        // Flagged anomaly records with details and risk severity
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_anomalies (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                cashier_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                snapshot_id UUID REFERENCES cashier_performance_snapshots(id) ON DELETE SET NULL,
                anomaly_type VARCHAR(50) NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT 'medium',
                risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                title_en VARCHAR(255) NOT NULL,
                title_ar VARCHAR(255) NOT NULL,
                description_en TEXT,
                description_ar TEXT,
                metric_name VARCHAR(50) NOT NULL,
                metric_value DECIMAL(12,2) NOT NULL,
                store_average DECIMAL(12,2) NOT NULL DEFAULT 0,
                store_stddev DECIMAL(12,2) NOT NULL DEFAULT 0,
                z_score DECIMAL(8,2) NOT NULL DEFAULT 0,
                reference_ids {$jsonType} DEFAULT NULL,
                is_reviewed BOOLEAN NOT NULL DEFAULT FALSE,
                reviewed_by UUID REFERENCES users(id),
                reviewed_at TIMESTAMP,
                review_notes TEXT,
                detected_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT {$nowDefault},
                updated_at TIMESTAMP DEFAULT {$nowDefault}
            )
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_anomalies_store_date
            ON cashier_anomalies (store_id, detected_date DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_anomalies_unreviewed
            ON cashier_anomalies (store_id, is_reviewed, severity)
        ");

        // ─── Cashier Shift Reports ──────────────────────────────────────
        // Generated shift-end report cards for owners
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_shift_reports (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                pos_session_id UUID REFERENCES pos_sessions(id) ON DELETE SET NULL,
                cashier_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                report_date DATE NOT NULL,
                shift_start TIMESTAMP,
                shift_end TIMESTAMP,

                -- Summary Metrics
                total_transactions INTEGER NOT NULL DEFAULT 0,
                total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_items INTEGER NOT NULL DEFAULT 0,
                items_per_minute DECIMAL(8,2) NOT NULL DEFAULT 0,
                avg_basket_size DECIMAL(12,2) NOT NULL DEFAULT 0,
                void_count INTEGER NOT NULL DEFAULT 0,
                void_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                return_count INTEGER NOT NULL DEFAULT 0,
                return_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_count INTEGER NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                no_sale_count INTEGER NOT NULL DEFAULT 0,
                price_override_count INTEGER NOT NULL DEFAULT 0,
                cash_variance DECIMAL(12,2) NOT NULL DEFAULT 0,
                upsell_count INTEGER NOT NULL DEFAULT 0,
                upsell_rate DECIMAL(5,4) NOT NULL DEFAULT 0,

                -- Assessment
                risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                risk_level VARCHAR(20) NOT NULL DEFAULT 'normal',
                anomaly_count INTEGER NOT NULL DEFAULT 0,
                badges_earned {$jsonType} DEFAULT NULL,
                summary_en TEXT,
                summary_ar TEXT,

                -- Notification
                sent_to_owner BOOLEAN NOT NULL DEFAULT FALSE,
                sent_at TIMESTAMP,

                created_at TIMESTAMP DEFAULT {$nowDefault},
                updated_at TIMESTAMP DEFAULT {$nowDefault}
            )
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_shift_reports_store_date
            ON cashier_shift_reports (store_id, report_date DESC)
        ");

        // ─── Cashier Gamification Settings ──────────────────────────────
        // Per-store configuration for the gamification feature
        DB::statement("
            CREATE TABLE IF NOT EXISTS cashier_gamification_settings (
                id UUID PRIMARY KEY DEFAULT {$uuidDefault},
                store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                leaderboard_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                badges_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                anomaly_detection_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                shift_reports_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                auto_generate_on_session_close BOOLEAN NOT NULL DEFAULT TRUE,
                risk_score_void_weight DECIMAL(5,2) NOT NULL DEFAULT 30,
                risk_score_no_sale_weight DECIMAL(5,2) NOT NULL DEFAULT 25,
                risk_score_discount_weight DECIMAL(5,2) NOT NULL DEFAULT 25,
                risk_score_price_override_weight DECIMAL(5,2) NOT NULL DEFAULT 20,
                anomaly_z_score_threshold DECIMAL(5,2) NOT NULL DEFAULT 2.0,
                created_at TIMESTAMP DEFAULT {$nowDefault},
                updated_at TIMESTAMP DEFAULT {$nowDefault},
                UNIQUE(store_id)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS cashier_gamification_settings CASCADE');
        DB::statement('DROP TABLE IF EXISTS cashier_shift_reports CASCADE');
        DB::statement('DROP TABLE IF EXISTS cashier_anomalies CASCADE');
        DB::statement('DROP TABLE IF EXISTS cashier_badge_awards CASCADE');
        DB::statement('DROP TABLE IF EXISTS cashier_badges CASCADE');
        DB::statement('DROP TABLE IF EXISTS cashier_performance_snapshots CASCADE');
    }
};
