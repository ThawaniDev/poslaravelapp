<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isSuccess ? 'تم الدفع بنجاح' : 'فشل الدفع' }} - Wameed POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #F8F7F5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
        .header {
            padding: 40px 24px 32px;
            text-align: center;
        }
        .header.success { background: linear-gradient(135deg, #10B981, #059669); }
        .header.failed { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .header.pending { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 36px;
        }
        .header h1 {
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .header p {
            color: rgba(255,255,255,.85);
            font-size: 14px;
        }
        .body { padding: 32px 24px; }
        .amount-box {
            text-align: center;
            padding: 20px;
            background: #F8FAFC;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .amount-box .label {
            color: #64748B;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .amount-box .value {
            font-size: 32px;
            font-weight: 800;
            color: #0F172A;
        }
        .amount-box .currency {
            font-size: 16px;
            font-weight: 600;
            color: #64748B;
            margin-right: 4px;
        }
        .conversion-note {
            text-align: center;
            color: #6366F1;
            font-size: 13px;
            margin-top: 8px;
        }
        .details { margin-bottom: 24px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label { color: #64748B; }
        .detail-row .value { color: #0F172A; font-weight: 600; text-align: left; direction: ltr; }
        .detail-row .value.mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; }
        .footer {
            text-align: center;
            padding: 0 24px 32px;
        }
        .footer p {
            color: #94A3B8;
            font-size: 12px;
            margin-top: 16px;
        }
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .logo-text {
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #FD8209, #FFBF0D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header {{ $isSuccess ? 'success' : ($isPending ? 'pending' : 'failed') }}">
            <div class="icon">
                @if($isSuccess)
                    ✓
                @elseif($isPending)
                    ⏳
                @else
                    ✕
                @endif
            </div>
            <h1>
                @if($isSuccess)
                    {{ __('subscription.payment_success_title') }}
                @elseif($isPending)
                    {{ __('subscription.payment_pending_title') }}
                @else
                    {{ __('subscription.payment_failed_title') }}
                @endif
            </h1>
            <p>
                @if($isSuccess)
                    {{ __('subscription.payment_success_sub') }}
                @elseif($isPending)
                    {{ __('subscription.payment_pending_sub') }}
                @else
                    {{ __('subscription.payment_failed_sub') }}
                @endif
            </p>
        </div>

        <div class="body">
            @if($payment)
            <div class="amount-box">
                <div class="label">{{ __('subscription.payment_total_amount') }}</div>
                <div class="value">
                    {{ number_format((float) $payment->total_amount, 2) }}
                    <span class="currency">{{ $payment->currency }}</span>
                </div>
                @if($payment->hasOriginalCurrency())
                    <div class="conversion-note">
                        ${{ number_format((float) $payment->original_amount, 2) }} USD × {{ number_format((float) $payment->exchange_rate_used, 4) }} = {{ number_format((float) $payment->amount, 2) }} SAR
                    </div>
                @endif
            </div>

            <div class="details">
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_purpose') }}</span>
                    <span class="value">{{ $payment->purpose_label ?? $payment->purpose?->label() ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_subtotal') }}</span>
                    <span class="value">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_vat') }}</span>
                    <span class="value">{{ number_format((float) $payment->tax_amount, 2) }} {{ $payment->currency }}</span>
                </div>
                @if($payment->tran_ref)
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_reference') }}</span>
                    <span class="value mono">{{ $payment->tran_ref }}</span>
                </div>
                @endif
                @if($payment->cart_id)
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_order_id') }}</span>
                    <span class="value mono">{{ $payment->cart_id }}</span>
                </div>
                @endif
                @if($payment->payment_description)
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_card') }}</span>
                    <span class="value">{{ $payment->payment_description }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_date') }}</span>
                    <span class="value">{{ $payment->updated_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}</span>
                </div>
                @if(!$isSuccess && $payment->response_message)
                <div class="detail-row">
                    <span class="label">{{ __('subscription.payment_reason') }}</span>
                    <span class="value" style="color: #EF4444;">{{ $payment->response_message }}</span>
                </div>
                @endif
            </div>
            @else
            <div class="amount-box">
                <div class="label">{{ __('subscription.payment_not_available') }}</div>
                <div class="value" style="font-size: 16px; color: #64748B;">{{ __('subscription.payment_not_available') }}</div>
            </div>
            @endif
        </div>

        <div class="footer">
            <div class="logo">
                <span class="logo-text">{{ __('ui.app_name') }}</span>
            </div>
            <p>{!! __('subscription.payment_footer') !!}</p>
        </div>
    </div>
</body>
</html>
