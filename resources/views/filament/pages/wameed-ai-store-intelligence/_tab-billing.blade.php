            {{-- Billing KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Invoiced</p>
                        <p class="text-3xl font-bold text-primary-600">${{ number_format($billing['totalInvoiced'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($billing['totalPaid'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.pending') }}</p>
                        <p class="text-3xl font-bold text-warning-600">${{ number_format($billing['totalPending'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
                        <p class="text-3xl font-bold text-danger-600">${{ number_format($billing['totalOverdue'], 4) }}</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Current Month Running --}}
            <x-filament::section heading="Current Month (Running)" class="mt-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Requests</p>
                        <p class="text-2xl font-bold text-primary-600">{{ number_format($billing['currentMonthRequests']) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Raw Cost</p>
                        <p class="text-2xl font-bold text-warning-600">${{ number_format($billing['currentMonthRawCost'], 4) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Billed Cost</p>
                        <p class="text-2xl font-bold text-success-600">${{ number_format($billing['currentMonthBilledCost'], 4) }}</p>
                    </div>
                </div>
                @if ($billing['billingConfig'] && $billing['billingConfig']->monthly_limit_usd > 0)
                    @php
                        $limit = $billing['billingConfig']->monthly_limit_usd;
                        $usedPct = min(100, round($billing['currentMonthBilledCost'] / $limit * 100, 1));
                    @endphp
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>Monthly Limit: ${{ number_format($limit, 2) }}</span>
                            <span>{{ $usedPct }}% used</span>
                        </div>
                        <div class="h-3 rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-3 rounded-full {{ $usedPct >= 90 ? 'bg-danger-500' : ($usedPct >= 70 ? 'bg-warning-500' : 'bg-success-500') }}" style="width: {{ $usedPct }}%"></div>
                        </div>
                    </div>
                @endif
            </x-filament::section>

            {{-- Billing Configuration --}}
            <x-filament::section heading="Billing Configuration" class="mt-4">
                @if ($billing['billingConfig'])
                    @php $bc = $billing['billingConfig']; @endphp
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                        <div>
                            <span class="text-gray-500">AI Enabled</span>
                            <p class="font-medium">{{ $bc->is_ai_enabled ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Monthly Limit</span>
                            <p class="font-medium">{{ $bc->monthly_limit_usd > 0 ? '$' . number_format($bc->monthly_limit_usd, 2) : 'No limit' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Custom Margin</span>
                            <p class="font-medium">{{ $bc->custom_margin_percentage !== null ? $bc->custom_margin_percentage . '%' : 'Platform default' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Notes</span>
                            <p class="text-xs">{{ $bc->notes ?? '—' }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400">No billing configuration.</p>
                @endif
            </x-filament::section>

            {{-- Invoice History --}}
            <x-filament::section heading="Invoice History" class="mt-4">
                @if ($billing['invoices']->isEmpty())
                    <p class="text-sm text-gray-400">No invoices yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.invoice_number') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.period') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_percentage') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_dollar') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.due_date') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Paid At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['invoices'] as $invoice)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                                        <td class="px-3 py-2">{{ $invoice->year }}-{{ str_pad($invoice->month, 2, '0', STR_PAD_LEFT) }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($invoice->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($invoice->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($invoice->raw_cost_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($invoice->margin_percentage, 1) }}%</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($invoice->margin_amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-medium">${{ number_format($invoice->billed_amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $invoice->status === 'paid',
                                                'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $invoice->status === 'pending',
                                                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $invoice->status === 'overdue',
                                                'bg-gray-50 text-gray-700 dark:bg-gray-500/10 dark:text-gray-400' => ! in_array($invoice->status, ['paid', 'pending', 'overdue']),
                                            ])>{{ ucfirst($invoice->status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->paid_at?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Latest Invoice Items --}}
            @if ($billing['latestInvoice'])
                <x-filament::section heading="Latest Invoice Breakdown: {{ $billing['latestInvoice']->invoice_number }}" class="mt-4">
                    @if ($billing['latestInvoiceItems']->isEmpty())
                        <p class="text-sm text-gray-400">No line items.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">Feature (AR)</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($billing['latestInvoiceItems'] as $item)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="px-3 py-2">{{ $item->feature_name ?? $item->feature_slug }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">{{ $item->feature_name_ar ?? '—' }}</td>
                                            <td class="px-3 py-2 text-end font-mono">{{ number_format($item->request_count) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($item->total_tokens) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($item->raw_cost_usd, 4) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($item->billed_cost_usd, 4) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Payment History --}}
            <x-filament::section heading="Payment History" class="mt-4">
                @if ($billing['payments']->isEmpty())
                    <p class="text-sm text-gray-400">No payments recorded.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Date</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Amount</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Method</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Reference</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['payments'] as $payment)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 text-xs">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-medium text-success-600">${{ number_format($payment->amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $payment->payment_method ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs font-mono">{{ $payment->reference ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $payment->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Monthly Summary Trend --}}
            <x-filament::section heading="Monthly Usage Trend" class="mt-4">
                @if ($billing['monthlySummaries']->isEmpty())
                    <p class="text-sm text-gray-400">No monthly summaries available.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Month</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Cached</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Failed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['monthlySummaries'] as $summary)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ $summary->month }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($summary->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($summary->cached_requests) }}</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($summary->failed_requests > 0)
                                                <span class="text-danger-600 font-medium">{{ number_format($summary->failed_requests) }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format(($summary->total_input_tokens ?? 0) + ($summary->total_output_tokens ?? 0)) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($summary->total_estimated_cost_usd, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
