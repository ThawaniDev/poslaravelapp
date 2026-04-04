<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Wameed POS — Formatting Helpers
 *
 * Mirrors Flutter's Formatters class. Use in Blade, API resources,
 * and Filament columns/infolists for consistent display.
 */
final class Formatters
{
    // ─── Currency ──────────────────────────────────────────

    /** 1234.567 → "ر.س. 1,234.567" */
    public static function currency(float $amount, string $symbol = 'ر.س.'): string
    {
        return $symbol . ' ' . number_format($amount, DesignTokens::CURRENCY_DECIMAL);
    }

    /** Short (no decimals): 1234 → "ر.س. 1,234" */
    public static function currencyShort(float $amount, string $symbol = 'ر.س.'): string
    {
        return $symbol . ' ' . number_format($amount, 0);
    }

    /** Compact: 12500 → "12.5K" */
    public static function currencyCompact(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return number_format($amount / 1_000_000, 1) . 'M';
        }
        if ($amount >= 1_000) {
            return number_format($amount / 1_000, 1) . 'K';
        }
        return number_format($amount, DesignTokens::CURRENCY_DECIMAL);
    }

    // ─── Dates ─────────────────────────────────────────────

    public static function date(Carbon|string $dt, string $format = null): string
    {
        $carbon = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
        return $carbon->format($format ?? DesignTokens::DATE_DISPLAY);
    }

    public static function dateIso(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::DATE_ISO);
    }

    public static function dateFull(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::DATE_FULL);
    }

    public static function dateMedium(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::DATE_MEDIUM);
    }

    public static function dateTime(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::DATETIME);
    }

    public static function time(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::TIME_24);
    }

    public static function time12(Carbon|string $dt): string
    {
        return self::date($dt, DesignTokens::TIME_12);
    }

    // ─── Relative Time ─────────────────────────────────────

    public static function timeAgo(Carbon|string $dt): string
    {
        $carbon = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
        return $carbon->diffForHumans();
    }

    // ─── Numbers ───────────────────────────────────────────

    public static function number(int|float $value): string
    {
        return number_format($value);
    }

    public static function percent(float $value, int $decimals = 1): string
    {
        return number_format($value * 100, $decimals) . '%';
    }

    public static function compact(int|float $value): string
    {
        if ($value >= 1_000_000) return number_format($value / 1_000_000, 1) . 'M';
        if ($value >= 1_000) return number_format($value / 1_000, 1) . 'K';
        return (string) $value;
    }

    // ─── File Size ─────────────────────────────────────────

    public static function fileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return number_format($bytes / 1_048_576, 1) . ' MB';
        return number_format($bytes / 1_073_741_824, 2) . ' GB';
    }

    // ─── Phone ─────────────────────────────────────────────

    public static function omanPhone(string $raw): string
    {
        $digits = preg_replace('/[^\d]/', '', $raw);
        if (strlen($digits) === 8) {
            return '+968 ' . substr($digits, 0, 4) . ' ' . substr($digits, 4);
        }
        if (strlen($digits) > 8 && str_starts_with($digits, '968')) {
            $local = substr($digits, 3);
            return '+968 ' . substr($local, 0, 4) . ' ' . substr($local, 4);
        }
        return $raw;
    }

    // ─── Ordinal ───────────────────────────────────────────

    public static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };
        return $n . $suffix;
    }
}
