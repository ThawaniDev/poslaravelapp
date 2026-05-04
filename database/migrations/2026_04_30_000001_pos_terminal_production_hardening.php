<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS Terminal — Production-readiness hardening migration (2026-04-30).
 *
 * Adds columns supporting:
 *   • Manager-PIN discount/refund threshold (`discount_pin_threshold_percent`)
 *   • Tip percentage presets shown on the payment screen
 *   • Customer-Facing-Display configuration on store_settings
 *   • Open Tab ↔ Transaction link (auto-close tab when paid)
 *   • Per-line modifier total on transaction_items (price impact of
 *     selected modifier_options, derived from `modifier_selections` JSON)
 *
 * All additions are additive and idempotent so this can be run safely on
 * production without affecting existing data.
 */
return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        // ── store_settings: PIN threshold + tip presets + CFD config ─────
        if (Schema::hasTable('store_settings')) {
            Schema::table('store_settings', function (Blueprint $t) {
                if (!Schema::hasColumn('store_settings', 'discount_pin_threshold_percent')) {
                    // Discount % above which a manager-PIN approval token is required.
                    // 0 means "always require PIN when require_manager_for_discount=true".
                    $t->integer('discount_pin_threshold_percent')->default(10);
                }
                if (!Schema::hasColumn('store_settings', 'tip_presets')) {
                    // JSON array of tip percentages e.g. [10, 15, 20, 25]
                    $t->json('tip_presets')->nullable();
                }
                if (!Schema::hasColumn('store_settings', 'tip_default_preset_index')) {
                    $t->integer('tip_default_preset_index')->nullable();
                }
                if (!Schema::hasColumn('store_settings', 'cfd_enabled')) {
                    $t->boolean('cfd_enabled')->default(false);
                }
                if (!Schema::hasColumn('store_settings', 'cfd_idle_layout')) {
                    // Values from CfdIdleLayout enum (e.g. 'logo', 'promotions', 'slideshow')
                    $t->string('cfd_idle_layout', 30)->default('logo');
                }
                if (!Schema::hasColumn('store_settings', 'cfd_cart_layout')) {
                    // Values from CfdCartLayout enum: 'list' | 'grid'
                    $t->string('cfd_cart_layout', 30)->default('list');
                }
                if (!Schema::hasColumn('store_settings', 'cfd_welcome_message')) {
                    $t->string('cfd_welcome_message', 200)->nullable();
                }
                if (!Schema::hasColumn('store_settings', 'cfd_welcome_message_ar')) {
                    $t->string('cfd_welcome_message_ar', 200)->nullable();
                }
                if (!Schema::hasColumn('store_settings', 'cfd_logo_url')) {
                    $t->string('cfd_logo_url', 500)->nullable();
                }
                if (!Schema::hasColumn('store_settings', 'cfd_show_promotions')) {
                    $t->boolean('cfd_show_promotions')->default(true);
                }
            });
        }

        // ── open_tabs: link tab to a settled transaction so we can auto-close ─
        if (Schema::hasTable('open_tabs') && !Schema::hasColumn('open_tabs', 'transaction_id')) {
            Schema::table('open_tabs', function (Blueprint $t) {
                $t->uuid('transaction_id')->nullable()->index();
                $t->decimal('running_total', 12, 2)->default(0);
            });
        }

        // ── transactions: optional tab link (sale paid against an open tab) ──
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'tab_id')) {
            Schema::table('transactions', function (Blueprint $t) {
                $t->uuid('tab_id')->nullable()->index();
            });
        }

        // ── transaction_items: cache modifier price impact for reporting ──
        if (Schema::hasTable('transaction_items')) {
            if (!Schema::hasColumn('transaction_items', 'modifier_total')) {
                Schema::table('transaction_items', function (Blueprint $t) {
                    $t->decimal('modifier_total', 12, 2)->default(0);
                });
            }
            // `modifier_selections` JSONB exists already from earlier migration —
            // no-op here.
            if (!Schema::hasColumn('transaction_items', 'item_notes')) {
                Schema::table('transaction_items', function (Blueprint $t) {
                    $t->text('item_notes')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_settings')) {
            Schema::table('store_settings', function (Blueprint $t) {
                foreach ([
                    'discount_pin_threshold_percent', 'tip_presets', 'tip_default_preset_index',
                    'cfd_enabled', 'cfd_idle_layout', 'cfd_cart_layout',
                    'cfd_welcome_message', 'cfd_welcome_message_ar',
                    'cfd_logo_url', 'cfd_show_promotions',
                ] as $c) {
                    if (Schema::hasColumn('store_settings', $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('open_tabs')) {
            Schema::table('open_tabs', function (Blueprint $t) {
                if (Schema::hasColumn('open_tabs', 'transaction_id')) $t->dropColumn('transaction_id');
                if (Schema::hasColumn('open_tabs', 'running_total')) $t->dropColumn('running_total');
            });
        }
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'tab_id')) {
            Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn('tab_id'));
        }
        if (Schema::hasTable('transaction_items')) {
            Schema::table('transaction_items', function (Blueprint $t) {
                if (Schema::hasColumn('transaction_items', 'modifier_total')) $t->dropColumn('modifier_total');
                if (Schema::hasColumn('transaction_items', 'item_notes')) $t->dropColumn('item_notes');
            });
        }
    }
};
