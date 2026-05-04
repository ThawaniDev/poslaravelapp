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
 *  │  Visa /     │  Fixed SAR amount per transaction                    │
 *  │  Mastercard │    platform_fee = card_merchant_fee                  │
 *  │             │    gateway_fee  = card_gateway_fee                   │
 *  │             │    margin       = platform_fee − gateway_fee          │
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

        if (in_array($v, self::MADA_SCHEMES, true)) {
            return 'mada';
        }
        if (str_contains($v, 'mada')) {
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
            madaMerchantRate:  (float) ($register->softpos_mada_merchant_rate ?? 0.006),
            madaGatewayRate:   (float) ($register->softpos_mada_gateway_rate  ?? 0.004),
            cardMerchantFee:   (float) ($register->softpos_card_merchant_fee  ?? 1.000),
            cardGatewayFee:    (float) ($register->softpos_card_gateway_fee   ?? 0.500),
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
        float  $cardMerchantFee,
        float  $cardGatewayFee,
    ): array {
        $scheme = $this->normaliseScheme($cardScheme);

        if ($this->isMada($scheme)) {
            $platformFee = round($amount * $madaMerchantRate, 3);
            $gatewayFee  = round($amount * $madaGatewayRate, 3);
            $feeType     = 'percentage';
        } elseif ($this->isCard($scheme)) {
            $platformFee = round($cardMerchantFee, 3);
            $gatewayFee  = round($cardGatewayFee, 3);
            $feeType     = 'fixed';
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
     *   "1.000 SAR per Visa/MC transaction"
     */
    public function merchantFeeDescription(
        ?string $cardScheme,
        float   $madaMerchantRate,
        float   $cardMerchantFee,
    ): string {
        if ($this->isMada($cardScheme)) {
            return round($madaMerchantRate * 100, 4) . '% per Mada transaction';
        }

        return number_format($cardMerchantFee, 3) . ' SAR per Visa/MC transaction';
    }

    /**
     * Build a short internal description for admin use.
     *
     * Example: "Rate: 0.6% / 0.4% — margin 0.2% (Mada)"
     */
    public function adminFeeDescription(
        ?string $cardScheme,
        float   $madaMerchantRate,
        float   $madaGatewayRate,
        float   $cardMerchantFee,
        float   $cardGatewayFee,
    ): string {
        if ($this->isMada($cardScheme)) {
            $merchant = round($madaMerchantRate * 100, 4);
            $gateway  = round($madaGatewayRate * 100, 4);
            $margin   = round(($madaMerchantRate - $madaGatewayRate) * 100, 4);
            return "Merchant {$merchant}% / Gateway {$gateway}% / Margin {$margin}% (Mada)";
        }

        $margin = round($cardMerchantFee - $cardGatewayFee, 3);
        return "Merchant {$cardMerchantFee} SAR / Gateway {$cardGatewayFee} SAR / Margin {$margin} SAR (Visa/MC)";
    }
}
