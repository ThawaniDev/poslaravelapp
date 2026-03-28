<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Models\CfdTheme;
use Illuminate\Database\Seeder;

class ComprehensiveCfdThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            // ── 1. Clean White (existing, enhanced) ──
            [
                'name'                => 'Clean White',
                'slug'                => 'cfd_clean_white',
                'background_color'    => '#FFFFFF',
                'text_color'          => '#1F2937',
                'accent_color'        => '#2563EB',
                'font_family'         => 'Inter',
                'cart_layout'         => 'list',
                'idle_layout'         => 'slideshow',
                'animation_style'     => 'fade',
                'transition_seconds'  => 5,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
            // ── 2. Dark Elegant (existing) ──
            [
                'name'                => 'Dark Elegant',
                'slug'                => 'cfd_dark_elegant',
                'background_color'    => '#0F172A',
                'text_color'          => '#E2E8F0',
                'accent_color'        => '#F59E0B',
                'font_family'         => 'Poppins',
                'cart_layout'         => 'grid',
                'idle_layout'         => 'static_image',
                'animation_style'     => 'slide',
                'transition_seconds'  => 3,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'check',
                'is_active'           => true,
            ],
            // ── 3. Neon Glow – vibrant color-pop for trendy stores ──
            [
                'name'                => 'Neon Glow',
                'slug'                => 'cfd_neon_glow',
                'background_color'    => '#0A0A0A',
                'text_color'          => '#E0F2FE',
                'accent_color'        => '#06D6A0',
                'font_family'         => 'Orbitron',
                'cart_layout'         => 'grid',
                'idle_layout'         => 'video_loop',
                'animation_style'     => 'slide',
                'transition_seconds'  => 4,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
            // ── 4. Corporate Blue – professional business look ──
            [
                'name'                => 'Corporate Blue',
                'slug'                => 'cfd_corporate_blue',
                'background_color'    => '#EFF6FF',
                'text_color'          => '#1E3A5F',
                'accent_color'        => '#1D4ED8',
                'font_family'         => 'Roboto',
                'cart_layout'         => 'list',
                'idle_layout'         => 'static_image',
                'animation_style'     => 'fade',
                'transition_seconds'  => 6,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'check',
                'is_active'           => true,
            ],
            // ── 5. Fresh Green – eco/organic/natural stores ──
            [
                'name'                => 'Fresh Green',
                'slug'                => 'cfd_fresh_green',
                'background_color'    => '#F0FDF4',
                'text_color'          => '#14532D',
                'accent_color'        => '#16A34A',
                'font_family'         => 'Nunito',
                'cart_layout'         => 'list',
                'idle_layout'         => 'slideshow',
                'animation_style'     => 'fade',
                'transition_seconds'  => 5,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'check',
                'is_active'           => true,
            ],
            // ── 6. Warm Sunset – warm inviting ambiance ──
            [
                'name'                => 'Warm Sunset',
                'slug'                => 'cfd_warm_sunset',
                'background_color'    => '#FFF7ED',
                'text_color'          => '#7C2D12',
                'accent_color'        => '#EA580C',
                'font_family'         => 'Lato',
                'cart_layout'         => 'grid',
                'idle_layout'         => 'slideshow',
                'animation_style'     => 'slide',
                'transition_seconds'  => 4,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
            // ── 7. Royal Gold – luxury/jewelry/premium brand ──
            [
                'name'                => 'Royal Gold',
                'slug'                => 'cfd_royal_gold',
                'background_color'    => '#1C1917',
                'text_color'          => '#FEFCE8',
                'accent_color'        => '#CA8A04',
                'font_family'         => 'Playfair Display',
                'cart_layout'         => 'list',
                'idle_layout'         => 'static_image',
                'animation_style'     => 'fade',
                'transition_seconds'  => 6,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'check',
                'is_active'           => true,
            ],
            // ── 8. Ocean Wave – coastal/fresh/relaxed vibe ──
            [
                'name'                => 'Ocean Wave',
                'slug'                => 'cfd_ocean_wave',
                'background_color'    => '#ECFEFF',
                'text_color'          => '#164E63',
                'accent_color'        => '#0891B2',
                'font_family'         => 'Nunito',
                'cart_layout'         => 'list',
                'idle_layout'         => 'video_loop',
                'animation_style'     => 'slide',
                'transition_seconds'  => 5,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
            // ── 9. Minimalist Gray – ultra-clean professional ──
            [
                'name'                => 'Minimalist Gray',
                'slug'                => 'cfd_minimalist_gray',
                'background_color'    => '#F9FAFB',
                'text_color'          => '#374151',
                'accent_color'        => '#6B7280',
                'font_family'         => 'Inter',
                'cart_layout'         => 'list',
                'idle_layout'         => 'static_image',
                'animation_style'     => 'none',
                'transition_seconds'  => 0,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'none',
                'is_active'           => true,
            ],
            // ── 10. Festival Red – festive/celebration/seasonal ──
            [
                'name'                => 'Festival Red',
                'slug'                => 'cfd_festival_red',
                'background_color'    => '#FEF2F2',
                'text_color'          => '#7F1D1D',
                'accent_color'        => '#DC2626',
                'font_family'         => 'Poppins',
                'cart_layout'         => 'grid',
                'idle_layout'         => 'slideshow',
                'animation_style'     => 'slide',
                'transition_seconds'  => 3,
                'show_store_logo'     => true,
                'show_running_total'  => true,
                'thank_you_animation' => 'confetti',
                'is_active'           => true,
            ],
        ];

        foreach ($themes as $theme) {
            CfdTheme::updateOrCreate(['slug' => $theme['slug']], $theme);
        }
    }
}
