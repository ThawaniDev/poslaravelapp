<?php

namespace App\Domain\ProviderSubscription\Services;

use App\Domain\Core\Models\Register;

/**
 * Calculates the platform fee, gateway fee, and net margin for every SoftPOS
 * transaction, based on the bilateral rate configuration stored on the terminal
 * (Register).
 *
 * Two fee models are supported:
 *
 *  ┌─────────────┬──────────────────────────────────────────────────────┐
 *  │  Card type  │  Fee model                                           │
 *  ├─────────────┼──────────────────────────────────────────────────────┤
 *  │  Mada       │  Percentage of transaction amount                    │
 *  │             │    platform_fee = amount × merchant_rate             │
 *  │             │    gateway_fee  = amount × gateway_rate              │
 *  │             │    margin       = platform_fee − gateway_fee          │
 *  ├─────────────┼──────────────────────────────────────────────────────┤
 *  │  Visa /     │  Mixed: percentage + fixed SAR per transaction        │
 *  │  Mastercard │    platform_fee = (amount × card_merchant_rate)       │
 *  │             │                 + card_merchant_fee                  │
 *  │             │    gateway_fee  = (amount × card_gateway_rate)        │
 *  │             │                 + card_gateway_fee                   │
 *  │             │    margin       = platform_fee − gateway_fee          │
 *  │             │  When both rates = 0, degrades to fixed-only.         │
 *  └─────────────┴──────────────────────────────────────────────────────┘
 *
 * The merchant only sees `platform_fee` (what they are charged).
 * The `gateway_fee` and `margin` are kept internal.
 */
class SoftPosFeeService
{
    // ── Card scheme classification ───────────────────────────────────────

    private const MADA_SCHEMES = ['mada'];
    private const CARD_SCHEMES = ['visa', 'mastercard', 'master card', 'mc', 'master'];

    /**
     * Normalise a raw card scheme string to a canonical lower-case key.
     */
    public function normaliseScheme(?string $raw): string
    {
        $v = strtolower(trim($raw ?? ''));

        // Human-readable names
        if (in_array($v, self::MADA_SCHEMES, true) || str_contains($v, 'mada')) {
            return 'mada';
        }
        if (str_contains($v, 'visa')) {
            return 'visa';
        }
        if (str_contains($v, 'master') || $v === 'mc') {
            return 'mastercard';
        }
        if (str_contains($v, 'amex') || str_contains($v, 'american express')) {
            return 'amex';
        }

        // EdfaPay / EMV short codes returned by the SoftPOS SDK
        if ($v === 'p1') {             // mada (Proprietary 1, AID A0000002281010)
            return 'mada';
        }
        if ($v === 'vi') {             // Visa
            return 'visa';
        }
        if ($v === 'ax' || $v === 'ae') { // American Express
            return 'amex';
        }

        return $v ?: 'unknown';
    }

    public function isMada(?string $scheme): bool
    {
        return $this->normaliseScheme($scheme) === 'mada';
    }

    public function isCard(?string $scheme): bool
    {
        $n = $this->normaliseScheme($scheme);
        return in_array($n, ['visa', 'mastercard', 'amex'], true);
    }

    // ── Fee calculation ───────────────────────────────────────────────────

    /**
     * Calculate fees from a terminal (Register) record.
     *
     * @return array{
     *     platform_fee: float,
     *     gateway_fee:  float,
     *     margin:       float,
     *     fee_type:     string,   // 'percentage' | 'fixed' | 'unknown'
     *     scheme:       string,   // normalised card scheme
     * }
     */
    public function calculateFromRegister(
        float $amount,
        ?string $cardScheme,
        Register $register,
    ): array {
        return $this->calculate(
            amount:            $amount,
            cardScheme:        $cardScheme,
            madaMerchantRate:  (float) ($register->softpos_mada_merchant_rate  ?? 0.006),
            madaGatewayRate:   (float) ($register->softpos_mada_gateway_rate   ?? 0.004),
            cardMerchantRate:  (float) ($register->softpos_card_merchant_rate  ?? 0.0),
            cardGatewayRate:   (float) ($register->softpos_card_gateway_rate   ?? 0.0),
            cardMerchantFee:   (float) ($register->softpos_card_merchant_fee   ?? 1.000),
            cardGatewayFee:    (float) ($register->softpos_card_gateway_fee    ?? 0.500),
        );
    }

    /**
     * Calculate fees from explicit rate/fee values (also used in tests).
     *
     * @return array{
     *     platform_fee: float,
     *     gateway_fee:  float,
     *     margin:       float,
     *     fee_type:     string,
     *     scheme:       string,
     * }
     */
    public function calculate(
        float  $amount,
        ?string $cardScheme,
        float  $madaMerchantRate,
        float  $madaGatewayRate,
        float  $cardMerchantRate = 0.0,
        float  $cardGatewayRate  = 0.0,
        float  $cardMerchantFee  = 1.000,
        float  $cardGatewayFee   = 0.500,
    ): array {
        // Guard: negative/zero amounts (e.g. refunds) must not produce negative fees
        if ($amount <= 0) {
            return ['platform_fee' => 0.0, 'gateway_fee' => 0.0, 'margin' => 0.0, 'fee_type' => 'unknown', 'scheme' => $this->normaliseScheme($cardScheme)];
        }

        $scheme = $this->normaliseScheme($cardScheme);

        if ($this->isMada($scheme)) {
            $platformFee = round($amount * $madaMerchantRate, 3);
            $gatewayFee  = round($amount * $madaGatewayRate, 3);
            $feeType     = 'percentage';
        } elseif ($this->isCard($scheme)) {
            $platformFee = round(($amount * $cardMerchantRate) + $cardMerchantFee, 3);
            $gatewayFee  = round(($amount * $cardGatewayRate)  + $cardGatewayFee, 3);
            $feeType     = $cardMerchantRate > 0 ? 'mixed' : 'fixed';
        } else {
            // Unknown scheme — apply Mada rates as conservative default
            $platformFee = round($amount * $madaMerchantRate, 3);
            $gatewayFee  = round($amount * $madaGatewayRate, 3);
            $feeType     = 'percentage';
        }

        return [
            'platform_fee' => $platformFee,
            'gateway_fee'  => $gatewayFee,
            'margin'       => round($platformFee - $gatewayFee, 3),
            'fee_type'     => $feeType,
            'scheme'       => $scheme,
        ];
    }

    // ── Human-readable helpers ────────────────────────────────────────────

    /**
     * Build a short description of the merchant-facing fee for display.
     *
     * Examples:
     *   "0.6% per Mada transaction"
     *   "2.5% + 1.000 SAR per Visa/MC transaction"
     *   "1.000 SAR per Visa/MC transaction"
     */
    public function merchantFeeDescription(
        ?string $cardScheme,
        float   $madaMerchantRate,
        float   $cardMerchantRate = 0.0,
        float   $cardMerchantFee  = 1.000,
    ): string {
        if ($this->isMada($cardScheme)) {
            return round($madaMerchantRate * 100, 4) . '% per Mada transaction';
        }

        if ($cardMerchantRate > 0) {
            $pct = round($cardMerchantRate * 100, 4);
            return "{$pct}% + " . number_format($cardMerchantFee, 3) . ' SAR per Visa/MC transaction';
        }

        return number_format($cardMerchantFee, 3) . ' SAR per Visa/MC transaction';
    }

    /**
     * Build a short internal description for admin use.
     *
     * Example: "Merchant 0.6% / Gateway 0.4% / Margin 0.2% (Mada)"
     *          "Merchant 2.5%+1.000 SAR / Gateway 2.0%+0.500 SAR / Margin 0.5%+0.500 SAR (Visa/MC)"
     */
    public function adminFeeDescription(
        ?string $cardScheme,
        float   $madaMerchantRate,
        float   $madaGatewayRate,
        float   $cardMerchantRate = 0.0,
        float   $cardGatewayRate  = 0.0,
        float   $cardMerchantFee  = 1.000,
        float   $cardGatewayFee   = 0.500,
    ): string {
        if ($this->isMada($cardScheme)) {
            $merchant = round($madaMerchantRate * 100, 4);
            $gateway  = round($madaGatewayRate * 100, 4);
            $margin   = round(($madaMerchantRate - $madaGatewayRate) * 100, 4);
            return "Merchant {$merchant}% / Gateway {$gateway}% / Margin {$margin}% (Mada)";
        }

        if ($cardMerchantRate > 0) {
            $mPct = round($cardMerchantRate * 100, 4);
            $gPct = round($cardGatewayRate * 100, 4);
            return "Merchant {$mPct}%+{$cardMerchantFee} SAR / Gateway {$gPct}%+{$cardGatewayFee} SAR (Visa/MC)";
        }

        $margin = round($cardMerchantFee - $cardGatewayFee, 3);
        return "Merchant {$cardMerchantFee} SAR / Gateway {$cardGatewayFee} SAR / Margin {$margin} SAR (Visa/MC)";
    }
}
