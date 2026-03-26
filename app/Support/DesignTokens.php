<?php

namespace App\Support;

/**
 * Thawani POS — Design System Constants (Laravel side)
 *
 * Mirror of the Flutter AppColors / AppSpacing / AppSizes tokens.
 * Use in Filament resources, Blade views, and API responses that carry
 * status-colour metadata.
 */
final class DesignTokens
{
    // ─── Brand Colors ──────────────────────────────────────
    public const PRIMARY       = '#FD8209';
    public const PRIMARY_LIGHT = '#FFE8CC';
    public const PRIMARY_DARK  = '#C2530A';
    public const SECONDARY     = '#FFBF0D';

    // ─── Semantic Colors ───────────────────────────────────
    public const SUCCESS = '#10B981';
    public const WARNING = '#F59E0B';
    public const ERROR   = '#EF4444';
    public const INFO    = '#3B82F6';

    // ─── Backgrounds ───────────────────────────────────────
    public const BG_LIGHT = '#F8F7F5';
    public const BG_DARK  = '#23190F';

    // ─── Status → Color Maps ───────────────────────────────

    /** Order status colors */
    public const ORDER_STATUS_COLORS = [
        'pending'    => '#F59E0B',
        'confirmed'  => '#3B82F6',
        'preparing'  => '#A855F7',
        'ready'      => '#10B981',
        'delivered'  => '#059669',
        'cancelled'  => '#EF4444',
        'refunded'   => '#64748B',
    ];

    /** Stock status colors */
    public const STOCK_COLORS = [
        'in_stock' => '#22C55E',
        'low'      => '#F97316',
        'medium'   => '#F97316',
        'out'      => '#94A3B8',
    ];

    /** Tailwind class variants for badges */
    public const BADGE_CLASSES = [
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'error'   => 'bg-red-100 text-red-700',
        'info'    => 'bg-blue-100 text-blue-700',
        'neutral' => 'bg-slate-100 text-slate-600',
        'primary' => 'bg-orange-100 text-orange-700',
    ];

    // ─── Spacing (pixels) ──────────────────────────────────
    public const SPACING_XS   = 4;
    public const SPACING_SM   = 8;
    public const SPACING_MD   = 12;
    public const SPACING_BASE = 16;
    public const SPACING_LG   = 20;
    public const SPACING_XL   = 24;
    public const SPACING_XXL  = 32;

    // ─── Breakpoints ───────────────────────────────────────
    public const BREAKPOINT_MOBILE  = 640;
    public const BREAKPOINT_TABLET  = 768;
    public const BREAKPOINT_DESKTOP = 1024;
    public const BREAKPOINT_WIDE    = 1280;

    // ─── Currency ──────────────────────────────────────────
    public const CURRENCY_CODE    = 'SAR';
    public const CURRENCY_SYMBOL  = 'ر.ع.';
    public const CURRENCY_DECIMAL = 3;

    // ─── Date Formats ──────────────────────────────────────
    public const DATE_DISPLAY  = 'd/m/Y';       // 25/06/2025
    public const DATE_ISO      = 'Y-m-d';       // 2025-06-25
    public const DATE_FULL     = 'l, j F Y';    // Wednesday, 25 June 2025
    public const DATE_MEDIUM   = 'j M Y';       // 25 Jun 2025
    public const DATETIME      = 'd/m/Y H:i';   // 25/06/2025 14:35
    public const TIME_24       = 'H:i';          // 14:35
    public const TIME_12       = 'g:i A';        // 2:35 PM
}
