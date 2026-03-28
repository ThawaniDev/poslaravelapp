<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Enums\ThemeVariableCategory;
use App\Domain\ContentOnboarding\Enums\ThemeVariableType;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\ContentOnboarding\Models\ThemeVariable;
use Illuminate\Database\Seeder;

class ComprehensiveThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            // ── 1. Light Classic (existing) ──
            [
                'name'             => 'Light Classic',
                'slug'             => 'light_classic',
                'primary_color'    => '#1E40AF',
                'secondary_color'  => '#3B82F6',
                'background_color' => '#FFFFFF',
                'text_color'       => '#1F2937',
                'is_system'        => true,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Inter', 'base_size' => 14, 'heading_weight' => 600, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 8, 'width' => 1, 'color' => '#E5E7EB', 'focus_color' => '#3B82F6'],
                'shadow_config'     => ['card' => '0 1px 3px rgba(0,0,0,0.1)', 'dropdown' => '0 4px 12px rgba(0,0,0,0.15)', 'modal' => '0 8px 32px rgba(0,0,0,0.2)'],
                'animation_config'  => ['duration' => '200ms', 'easing' => 'ease-in-out', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#F9FAFB', '--muted' => '#6B7280', '--success' => '#059669', '--warning' => '#D97706', '--danger' => '#DC2626'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#1E40AF', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#3B82F6', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Inter, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '8px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 1px 3px rgba(0,0,0,0.1)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '4px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                ],
            ],
            // ── 2. Dark Mode (existing) ──
            [
                'name'             => 'Dark Mode',
                'slug'             => 'dark_mode',
                'primary_color'    => '#6366F1',
                'secondary_color'  => '#818CF8',
                'background_color' => '#111827',
                'text_color'       => '#F9FAFB',
                'is_system'        => true,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Inter', 'base_size' => 14, 'heading_weight' => 600, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 8, 'width' => 1, 'color' => '#374151', 'focus_color' => '#818CF8'],
                'shadow_config'     => ['card' => '0 2px 8px rgba(0,0,0,0.4)', 'dropdown' => '0 4px 16px rgba(0,0,0,0.5)', 'modal' => '0 8px 32px rgba(0,0,0,0.6)'],
                'animation_config'  => ['duration' => '200ms', 'easing' => 'ease-in-out', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#1F2937', '--muted' => '#9CA3AF', '--success' => '#34D399', '--warning' => '#FBBF24', '--danger' => '#F87171'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#6366F1', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#818CF8', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Inter, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '8px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 8px rgba(0,0,0,0.4)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '4px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                ],
            ],
            // ── 3. High Contrast (existing) ──
            [
                'name'             => 'High Contrast',
                'slug'             => 'high_contrast',
                'primary_color'    => '#000000',
                'secondary_color'  => '#FFDD00',
                'background_color' => '#FFFFFF',
                'text_color'       => '#000000',
                'is_system'        => true,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Arial', 'base_size' => 16, 'heading_weight' => 800, 'body_weight' => 500, 'line_height' => 1.6],
                'spacing_config'    => ['base' => 6, 'compact' => 4, 'comfortable' => 10, 'section_gap' => 20],
                'border_config'     => ['radius' => 4, 'width' => 2, 'color' => '#000000', 'focus_color' => '#FFDD00'],
                'shadow_config'     => ['card' => 'none', 'dropdown' => '0 2px 4px rgba(0,0,0,0.3)', 'modal' => '0 4px 16px rgba(0,0,0,0.4)'],
                'animation_config'  => ['duration' => '100ms', 'easing' => 'linear', 'hover_scale' => 1.0],
                'css_variables'     => ['--surface' => '#F5F5F5', '--muted' => '#333333', '--success' => '#006600', '--warning' => '#CC8800', '--danger' => '#CC0000'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#000000', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#FFDD00', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Arial, Helvetica, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '16px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '4px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                ],
            ],
            // ── 4. Thawani Brand (existing) ──
            [
                'name'             => 'Thawani Brand',
                'slug'             => 'thawani_brand',
                'primary_color'    => '#00B67A',
                'secondary_color'  => '#00D68F',
                'background_color' => '#F0FDF4',
                'text_color'       => '#14532D',
                'is_system'        => true,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Poppins', 'base_size' => 14, 'heading_weight' => 700, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 12, 'width' => 1, 'color' => '#BBF7D0', 'focus_color' => '#00B67A'],
                'shadow_config'     => ['card' => '0 2px 6px rgba(0,182,122,0.1)', 'dropdown' => '0 4px 12px rgba(0,182,122,0.15)', 'modal' => '0 8px 24px rgba(0,0,0,0.15)'],
                'animation_config'  => ['duration' => '250ms', 'easing' => 'cubic-bezier(0.4,0,0.2,1)', 'hover_scale' => 1.03],
                'css_variables'     => ['--surface' => '#ECFDF5', '--muted' => '#6B7280', '--success' => '#00B67A', '--warning' => '#EAB308', '--danger' => '#EF4444'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#00B67A', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#00D68F', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Poppins, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '12px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 6px rgba(0,182,122,0.1)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                ],
            ],
            // ── 5. Ocean Breeze (new) ──
            [
                'name'             => 'Ocean Breeze',
                'slug'             => 'ocean_breeze',
                'primary_color'    => '#0891B2',
                'secondary_color'  => '#06B6D4',
                'background_color' => '#ECFEFF',
                'text_color'       => '#164E63',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Nunito', 'base_size' => 14, 'heading_weight' => 700, 'body_weight' => 400, 'line_height' => 1.55],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 10, 'width' => 1, 'color' => '#A5F3FC', 'focus_color' => '#0891B2'],
                'shadow_config'     => ['card' => '0 2px 8px rgba(8,145,178,0.1)', 'dropdown' => '0 4px 16px rgba(8,145,178,0.15)', 'modal' => '0 8px 24px rgba(0,0,0,0.15)'],
                'animation_config'  => ['duration' => '300ms', 'easing' => 'cubic-bezier(0.4,0,0.2,1)', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#CFFAFE', '--muted' => '#0E7490', '--success' => '#059669', '--warning' => '#D97706', '--danger' => '#DC2626'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#0891B2', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#06B6D4', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Nunito, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '10px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 8px rgba(8,145,178,0.1)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '4px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                    ['variable_key' => '--transition-duration', 'variable_value' => '300ms', 'variable_type' => 'size', 'category' => 'animations'],
                ],
            ],
            // ── 6. Sunset Warmth (new) ──
            [
                'name'             => 'Sunset Warmth',
                'slug'             => 'sunset_warmth',
                'primary_color'    => '#EA580C',
                'secondary_color'  => '#F97316',
                'background_color' => '#FFF7ED',
                'text_color'       => '#7C2D12',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Lato', 'base_size' => 14, 'heading_weight' => 700, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 8, 'width' => 1, 'color' => '#FDBA74', 'focus_color' => '#EA580C'],
                'shadow_config'     => ['card' => '0 2px 8px rgba(234,88,12,0.1)', 'dropdown' => '0 4px 12px rgba(234,88,12,0.15)', 'modal' => '0 8px 24px rgba(0,0,0,0.18)'],
                'animation_config'  => ['duration' => '200ms', 'easing' => 'ease-out', 'hover_scale' => 1.03],
                'css_variables'     => ['--surface' => '#FED7AA', '--muted' => '#C2410C', '--success' => '#15803D', '--warning' => '#A16207', '--danger' => '#B91C1C'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#EA580C', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#F97316', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Lato, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '8px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 8px rgba(234,88,12,0.1)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                ],
            ],
            // ── 7. Forest Green (new) ──
            [
                'name'             => 'Forest Green',
                'slug'             => 'forest_green',
                'primary_color'    => '#15803D',
                'secondary_color'  => '#22C55E',
                'background_color' => '#F0FDF4',
                'text_color'       => '#14532D',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Source Sans Pro', 'base_size' => 14, 'heading_weight' => 600, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 18],
                'border_config'     => ['radius' => 6, 'width' => 1, 'color' => '#86EFAC', 'focus_color' => '#15803D'],
                'shadow_config'     => ['card' => '0 1px 4px rgba(21,128,61,0.08)', 'dropdown' => '0 4px 12px rgba(21,128,61,0.12)', 'modal' => '0 8px 24px rgba(0,0,0,0.15)'],
                'animation_config'  => ['duration' => '250ms', 'easing' => 'ease-in-out', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#DCFCE7', '--muted' => '#166534', '--success' => '#15803D', '--warning' => '#CA8A04', '--danger' => '#DC2626'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#15803D', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#22C55E', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Source Sans Pro, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '6px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                ],
            ],
            // ── 8. Royal Purple (new) ──
            [
                'name'             => 'Royal Purple',
                'slug'             => 'royal_purple',
                'primary_color'    => '#7C3AED',
                'secondary_color'  => '#A78BFA',
                'background_color' => '#F5F3FF',
                'text_color'       => '#3B0764',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Playfair Display', 'base_size' => 15, 'heading_weight' => 700, 'body_weight' => 400, 'line_height' => 1.6],
                'spacing_config'    => ['base' => 5, 'compact' => 3, 'comfortable' => 10, 'section_gap' => 20],
                'border_config'     => ['radius' => 12, 'width' => 1, 'color' => '#C4B5FD', 'focus_color' => '#7C3AED'],
                'shadow_config'     => ['card' => '0 2px 10px rgba(124,58,237,0.1)', 'dropdown' => '0 6px 18px rgba(124,58,237,0.15)', 'modal' => '0 10px 32px rgba(0,0,0,0.2)'],
                'animation_config'  => ['duration' => '300ms', 'easing' => 'cubic-bezier(0.4,0,0.2,1)', 'hover_scale' => 1.03],
                'css_variables'     => ['--surface' => '#EDE9FE', '--muted' => '#6D28D9', '--success' => '#059669', '--warning' => '#D97706', '--danger' => '#E11D48'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#7C3AED', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#A78BFA', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Playfair Display, Georgia, serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '15px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '12px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 10px rgba(124,58,237,0.1)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '5px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                ],
            ],
            // ── 9. Midnight Blue (new, dark theme) ──
            [
                'name'             => 'Midnight Blue',
                'slug'             => 'midnight_blue',
                'primary_color'    => '#1E3A5F',
                'secondary_color'  => '#3B82F6',
                'background_color' => '#0F172A',
                'text_color'       => '#E2E8F0',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Roboto', 'base_size' => 14, 'heading_weight' => 500, 'body_weight' => 400, 'line_height' => 1.5],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 16],
                'border_config'     => ['radius' => 8, 'width' => 1, 'color' => '#1E3A5F', 'focus_color' => '#3B82F6'],
                'shadow_config'     => ['card' => '0 2px 8px rgba(0,0,0,0.5)', 'dropdown' => '0 6px 20px rgba(0,0,0,0.6)', 'modal' => '0 10px 40px rgba(0,0,0,0.7)'],
                'animation_config'  => ['duration' => '250ms', 'easing' => 'ease-out', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#1E293B', '--muted' => '#94A3B8', '--success' => '#4ADE80', '--warning' => '#FBBF24', '--danger' => '#FB7185'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#1E3A5F', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#3B82F6', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Roboto, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '14px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '8px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 8px rgba(0,0,0,0.5)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '4px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                    ['variable_key' => '--transition-duration', 'variable_value' => '250ms', 'variable_type' => 'size', 'category' => 'animations'],
                ],
            ],
            // ── 10. Desert Sand (new, Saudi desert inspired) ──
            [
                'name'             => 'Desert Sand',
                'slug'             => 'desert_sand',
                'primary_color'    => '#B45309',
                'secondary_color'  => '#D97706',
                'background_color' => '#FFFBEB',
                'text_color'       => '#78350F',
                'is_system'        => false,
                'is_active'        => true,
                'typography_config' => ['font_family' => 'Noto Sans Arabic', 'base_size' => 15, 'heading_weight' => 700, 'body_weight' => 400, 'line_height' => 1.6],
                'spacing_config'    => ['base' => 4, 'compact' => 2, 'comfortable' => 8, 'section_gap' => 18],
                'border_config'     => ['radius' => 10, 'width' => 1, 'color' => '#FDE68A', 'focus_color' => '#B45309'],
                'shadow_config'     => ['card' => '0 2px 6px rgba(180,83,9,0.08)', 'dropdown' => '0 4px 12px rgba(180,83,9,0.12)', 'modal' => '0 8px 24px rgba(0,0,0,0.15)'],
                'animation_config'  => ['duration' => '200ms', 'easing' => 'ease-in-out', 'hover_scale' => 1.02],
                'css_variables'     => ['--surface' => '#FEF3C7', '--muted' => '#92400E', '--success' => '#059669', '--warning' => '#B45309', '--danger' => '#DC2626'],
                'variables' => [
                    ['variable_key' => '--primary', 'variable_value' => '#B45309', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--secondary', 'variable_value' => '#D97706', 'variable_type' => 'color', 'category' => 'colors'],
                    ['variable_key' => '--font-family', 'variable_value' => 'Noto Sans Arabic, system-ui, sans-serif', 'variable_type' => 'font', 'category' => 'typography'],
                    ['variable_key' => '--font-size-base', 'variable_value' => '15px', 'variable_type' => 'size', 'category' => 'typography'],
                    ['variable_key' => '--border-radius', 'variable_value' => '10px', 'variable_type' => 'border_radius', 'category' => 'borders'],
                    ['variable_key' => '--shadow-card', 'variable_value' => '0 2px 6px rgba(180,83,9,0.08)', 'variable_type' => 'shadow', 'category' => 'shadows'],
                    ['variable_key' => '--spacing-base', 'variable_value' => '4px', 'variable_type' => 'spacing', 'category' => 'spacing'],
                ],
            ],
        ];

        foreach ($themes as $data) {
            $variables = $data['variables'] ?? [];
            unset($data['variables']);

            $theme = Theme::updateOrCreate(['slug' => $data['slug']], $data);

            foreach ($variables as $var) {
                ThemeVariable::updateOrCreate(
                    ['theme_id' => $theme->id, 'variable_key' => $var['variable_key']],
                    $var,
                );
            }
        }
    }
}
