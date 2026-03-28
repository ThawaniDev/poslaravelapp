<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Add description columns to subscription_plans if missing
        if (!Schema::hasColumn('subscription_plans', 'description')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->text('description')->nullable()->after('slug');
                $table->text('description_ar')->nullable()->after('description');
            });
        }

        Schema::table('pricing_page_content', function (Blueprint $table) {
            // ── Hero / Display ───────────────────────────────────────────
            $table->string('hero_title')->nullable()->after('faq');
            $table->string('hero_title_ar')->nullable()->after('hero_title');
            $table->text('hero_subtitle')->nullable()->after('hero_title_ar');
            $table->text('hero_subtitle_ar')->nullable()->after('hero_subtitle');

            // ── Badge / Highlight ─────────────────────────────────────────
            $table->string('highlight_badge')->nullable()->after('hero_subtitle_ar');
            $table->string('highlight_badge_ar')->nullable()->after('highlight_badge');
            $table->string('highlight_color', 30)->nullable()->default('primary')->after('highlight_badge_ar');
            $table->boolean('is_highlighted')->default(false)->after('highlight_color');

            // ── CTA ───────────────────────────────────────────────────────
            $table->string('cta_label')->nullable()->after('is_highlighted');
            $table->string('cta_label_ar')->nullable()->after('cta_label');
            $table->string('cta_secondary_label')->nullable()->after('cta_label_ar');
            $table->string('cta_secondary_label_ar')->nullable()->after('cta_secondary_label');
            $table->string('cta_url')->nullable()->after('cta_secondary_label_ar');

            // ── Pricing Display ───────────────────────────────────────────
            $table->string('price_prefix')->nullable()->after('cta_url');
            $table->string('price_prefix_ar')->nullable()->after('price_prefix');
            $table->string('price_suffix')->nullable()->after('price_prefix_ar');
            $table->string('price_suffix_ar')->nullable()->after('price_suffix');
            $table->string('annual_discount_label')->nullable()->after('price_suffix_ar');
            $table->string('annual_discount_label_ar')->nullable()->after('annual_discount_label');
            $table->string('trial_label')->nullable()->after('annual_discount_label_ar');
            $table->string('trial_label_ar')->nullable()->after('trial_label');
            $table->unsignedSmallInteger('money_back_days')->nullable()->after('trial_label_ar');

            // ── Structured Feature Categories ─────────────────────────────
            // [{"category_en":"..","category_ar":"..","features":[{"text_en":"..","text_ar":"..","icon":"..","is_included":true,"is_highlighted":false,"tooltip_en":"..","tooltip_ar":".."}]}]
            $table->jsonb('feature_categories')->nullable()->default('[]')->after('money_back_days');

            // ── Testimonials ──────────────────────────────────────────────
            // [{"name":"..","role_en":"..","role_ar":"..","text_en":"..","text_ar":"..","rating":5,"avatar_url":"..","company":".."}]
            $table->jsonb('testimonials')->nullable()->default('[]')->after('feature_categories');

            // ── Comparison Highlights ─────────────────────────────────────
            // [{"feature_en":"..","feature_ar":"..","value":"..","note_en":"..","note_ar":".."}]
            $table->jsonb('comparison_highlights')->nullable()->default('[]')->after('testimonials');

            // ── SEO Meta ──────────────────────────────────────────────────
            $table->string('meta_title')->nullable()->after('comparison_highlights');
            $table->string('meta_title_ar')->nullable()->after('meta_title');
            $table->text('meta_description')->nullable()->after('meta_title_ar');
            $table->text('meta_description_ar')->nullable()->after('meta_description');

            // ── Visuals ───────────────────────────────────────────────────
            $table->string('color_theme', 30)->nullable()->default('primary')->after('meta_description_ar');
            $table->string('card_icon', 100)->nullable()->after('color_theme');
            $table->string('card_image_url')->nullable()->after('card_icon');

            // ── Publishing ────────────────────────────────────────────────
            $table->boolean('is_published')->default(true)->after('card_image_url');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_published');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('pricing_page_content', function (Blueprint $table) {
            $table->dropColumn([
                'hero_title', 'hero_title_ar', 'hero_subtitle', 'hero_subtitle_ar',
                'highlight_badge', 'highlight_badge_ar', 'highlight_color', 'is_highlighted',
                'cta_label', 'cta_label_ar', 'cta_secondary_label', 'cta_secondary_label_ar', 'cta_url',
                'price_prefix', 'price_prefix_ar', 'price_suffix', 'price_suffix_ar',
                'annual_discount_label', 'annual_discount_label_ar',
                'trial_label', 'trial_label_ar', 'money_back_days',
                'feature_categories', 'testimonials', 'comparison_highlights',
                'meta_title', 'meta_title_ar', 'meta_description', 'meta_description_ar',
                'color_theme', 'card_icon', 'card_image_url',
                'is_published', 'sort_order',
            ]);
        });

        if (Schema::hasColumn('subscription_plans', 'description')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn(['description', 'description_ar']);
            });
        }
    }
};
