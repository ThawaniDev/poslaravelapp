<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            padding: 40px;
            line-height: 1.5;
        }

        /* ─── Header ─── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #fd8209;
        }
        .brand-name {
            font-size: 26px;
            font-weight: 700;
            color: #fd8209;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .invoice-meta { text-align: right; }
        .invoice-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .invoice-number {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }
        .status-paid    { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed  { background: #fee2e2; color: #991b1b; }
        .status-draft   { background: #f1f5f9; color: #475569; }
        .status-refunded{ background: #ede9fe; color: #5b21b6; }

        /* ─── Parties ─── */
        .parties {
            display: flex;
            gap: 24px;
            margin-bottom: 28px;
        }
        .party-box {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
        }
        .party-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .party-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .party-detail {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ─── Dates ─── */
        .dates-row {
            display: flex;
            gap: 20px;
            margin-bottom: 28px;
        }
        .date-item {
            flex: 1;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .date-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
        }
        .date-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 3px;
        }

        /* ─── Line Items Table ─── */
        .table-section { margin-bottom: 28px; }
        .table-section h3 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
        }
        table { width: 100%; border-collapse: collapse; }
        thead tr {
            background: #0f172a;
            color: #f1f5f9;
        }
        thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        thead th:last-child { text-align: right; }
        thead th.num { text-align: center; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody td {
            padding: 11px 14px;
            font-size: 13px;
            color: #334155;
            vertical-align: top;
        }
        tbody td.num { text-align: center; color: #64748b; }
        tbody td.amount { text-align: right; font-weight: 600; color: #0f172a; }
        tfoot tr { border-top: 2px solid #e2e8f0; }
        tfoot td {
            padding: 8px 14px;
            font-size: 13px;
        }
        tfoot td.label { color: #64748b; text-align: right; }
        tfoot td.value { text-align: right; font-weight: 600; color: #0f172a; }
        .total-row td { font-size: 15px !important; font-weight: 700 !important; color: #fd8209 !important; }

        /* ─── Payment Info ─── */
        .payment-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .payment-box h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #475569;
            margin-bottom: 4px;
        }
        .payment-row span:last-child { font-weight: 600; color: #0f172a; }

        /* ─── Footer ─── */
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
        .footer strong { color: #475569; }

        /* ─── Watermark (paid) ─── */
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: 900;
            color: rgba(16, 185, 129, 0.08);
            text-transform: uppercase;
            letter-spacing: 8px;
            pointer-events: none;
            z-index: 0;
        }
    </style>
</head>
<body>

@if ($invoice->status?->value === 'paid' || $invoice->status === 'paid')
    <div class="watermark">{{ __('subscription.invoice_status_paid') }}</div>
@endif

{{-- ─── Header ─── --}}
<div class="header">
    <div>
        <div class="brand-name">{{ __('subscription.invoice_brand') }}</div>
        <div class="brand-sub">وميض نقاط البيع — {{ __('subscription.invoice_sub_brand') }}</div>
    </div>
    <div class="invoice-meta">
        <div class="invoice-title">{{ __('subscription.invoice_title') }}</div>
        <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
        @php
            $statusVal = $invoice->status?->value ?? $invoice->status;
            $statusClass = match($statusVal) {
                'paid'     => 'status-paid',
                'pending'  => 'status-pending',
                'failed'   => 'status-failed',
                'refunded' => 'status-refunded',
                default    => 'status-draft',
            };
        @endphp
        <span class="status-badge {{ $statusClass }}">{{ strtoupper($statusVal) }}</span>
    </div>
</div>

{{-- ─── Parties ─── --}}
<div class="parties">
    <div class="party-box">
        <div class="party-label">{{ __('subscription.invoice_from') }}</div>
        <div class="party-name">{{ __('subscription.issuer_company_name') }}</div>
        <div class="party-detail">Riyadh, Saudi Arabia</div>
        <div class="party-detail">VAT: 300000000000003</div>
    </div>
    <div class="party-box">
        <div class="party-label">{{ __('subscription.invoice_bill_to') }}</div>
        @if ($organization)
            <div class="party-name">{{ $organization->name }}</div>
            @if ($organization->owner ?? null)
                <div class="party-detail">{{ $organization->owner->email ?? '' }}</div>
            @endif
        @else
            <div class="party-name">—</div>
        @endif
    </div>
</div>

{{-- ─── Dates ─── --}}
<div class="dates-row">
    <div class="date-item">
        <div class="date-label">{{ __('subscription.invoice_issue_date') }}</div>
        <div class="date-value">{{ $invoice->created_at?->format('d M Y') ?? '—' }}</div>
    </div>
    <div class="date-item">
        <div class="date-label">{{ __('subscription.invoice_due_date') }}</div>
        <div class="date-value">{{ $invoice->due_date instanceof \DateTime ? $invoice->due_date->format('d M Y') : ($invoice->due_date ?? '—') }}</div>
    </div>
    @if ($invoice->paid_at)
    <div class="date-item">
        <div class="date-label">{{ __('subscription.invoice_payment_date') }}</div>
        <div class="date-value">{{ $invoice->paid_at->format('d M Y') }}</div>
    </div>
    @endif
    @if ($invoice->storeSubscription?->billing_cycle)
    <div class="date-item">
        <div class="date-label">{{ __('subscription.invoice_sub_brand') }}</div>
        <div class="date-value">{{ ucfirst($invoice->storeSubscription->billing_cycle?->value ?? $invoice->storeSubscription->billing_cycle) }}</div>
    </div>
    @endif
</div>

{{-- ─── Line Items ─── --}}
<div class="table-section">
    <h3>{{ __('subscription.invoice_items') }}</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('subscription.invoice_col_description') }}</th>
                <th class="num">{{ __('subscription.invoice_col_qty') }}</th>
                <th style="text-align:right">{{ __('subscription.invoice_col_unit_price') }}</th>
                <th style="text-align:right">{{ __('subscription.invoice_col_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->invoiceLineItems as $i => $item)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="amount">{{ number_format((float)$item->unit_price, 2) }}</td>
                <td class="amount">{{ number_format((float)$item->total, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td class="num">1</td>
                <td>
                    {{ $invoice->storeSubscription?->subscriptionPlan?->name ?? 'Subscription' }}
                    @if ($invoice->storeSubscription?->billing_cycle)
                        — {{ ucfirst($invoice->storeSubscription->billing_cycle?->value ?? $invoice->storeSubscription->billing_cycle) }}
                    @endif
                </td>
                <td class="num">1</td>
                <td class="amount">{{ number_format((float)$invoice->amount, 2) }}</td>
                <td class="amount">{{ number_format((float)$invoice->amount, 2) }}</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td class="label">{{ __('subscription.invoice_subtotal') }}</td>
                <td class="value">SAR {{ number_format((float)$invoice->amount, 2) }}</td>
            </tr>
            @if ((float)($invoice->tax ?? 0) > 0)
            <tr>
                <td colspan="3"></td>
                <td class="label">{{ __('subscription.invoice_vat') }}</td>
                <td class="value">SAR {{ number_format((float)$invoice->tax, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="3"></td>
                <td class="label" style="color:#fd8209!important;font-weight:700">{{ __('subscription.invoice_total_due') }}</td>
                <td class="value">SAR {{ number_format((float)$invoice->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- ─── Payment Info ─── --}}
@if ($invoice->gateway_transaction_ref || $invoice->payment_method)
<div class="payment-box">
    <h4>{{ __('subscription.invoice_payment_details') }}</h4>
    @if ($invoice->payment_method ?? $invoice->storeSubscription?->payment_method)
    <div class="payment-row">
        <span>{{ __('subscription.invoice_payment_method') }}</span>
        <span>{{ ucwords(str_replace('_', ' ', $invoice->payment_method ?? $invoice->storeSubscription?->payment_method ?? '')) }}</span>
    </div>
    @endif
    @if ($invoice->gateway_transaction_ref)
    <div class="payment-row">
        <span>{{ __('subscription.invoice_transaction_ref') }}</span>
        <span>{{ $invoice->gateway_transaction_ref }}</span>
    </div>
    @endif
    @if ($invoice->paid_at)
    <div class="payment-row">
        <span>{{ __('subscription.invoice_payment_date') }}</span>
        <span>{{ $invoice->paid_at->format('d M Y, H:i') }}</span>
    </div>
    @endif
</div>
@endif

{{-- ─── Footer ─── --}}
<div class="footer">
    <p>{{ __('subscription.invoice_footer_thanks') }}</p>
    <p style="margin-top:4px">{{ __('subscription.invoice_footer_billing') }}</p>
    <p style="margin-top:4px; color:#cbd5e1">{{ __('subscription.invoice_footer_generated') }}</p>
</div>

</body>
</html>
