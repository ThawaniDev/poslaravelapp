<x-filament-panels::page>
    {{-- Tab Navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
        @foreach (['overview' => 'Overview', 'invoices' => 'Invoices', 'stores' => 'Store Configs', 'settings' => 'Settings'] as $tab => $label)
            <button
                wire:click="setTab('{{ $tab }}')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                    'border-primary-500 text-primary-600 dark:text-primary-400' => $activeTab === $tab,
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== $tab,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Overview Tab --}}
    @if ($activeTab === 'overview')
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue (Billed)</p>
                    <p class="text-3xl font-bold text-success-600">${{ number_format($totalRevenue, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Raw Cost (OpenAI)</p>
                    <p class="text-3xl font-bold text-warning-600">${{ number_format($totalRawCost, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Platform Margin</p>
                    <p class="text-3xl font-bold text-primary-600">${{ number_format($totalMargin, 2) }}</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mt-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending Revenue</p>
                    <p class="text-2xl font-bold text-warning-600">${{ number_format($pendingRevenue, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">This Month (Billed)</p>
                    <p class="text-2xl font-bold text-primary-600">${{ number_format($currentMonthRevenue, 2) }}</p>
                    <p class="text-xs text-gray-400">Raw: ${{ number_format($currentMonthRawCost, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">AI-Enabled Stores</p>
                    <p class="text-2xl font-bold text-info-600">{{ $enabledStores }}/{{ $totalStores }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
                    <p class="text-2xl font-bold text-danger-600">{{ number_format($overdueInvoices) }}</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mt-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</p>
                    <p class="text-2xl font-bold">{{ number_format($totalInvoices) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Paid</p>
                    <p class="text-2xl font-bold text-success-600">{{ number_format($paidInvoices) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-warning-600">{{ number_format($pendingInvoices) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
                    <p class="text-2xl font-bold text-danger-600">{{ number_format($overdueInvoices) }}</p>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- Invoices Tab --}}
    @if ($activeTab === 'invoices')
        <x-filament::section heading="Recent Invoices">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Invoice #</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Store</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Period</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Margin %</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Margin $</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">Status</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-2 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                                <td class="px-3 py-2">{{ $invoice->store?->business_name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $invoice->year }}-{{ str_pad($invoice->month, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-2 text-end">{{ number_format($invoice->total_requests) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">${{ number_format($invoice->raw_cost_usd, 4) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($invoice->margin_percentage, 1) }}%</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($invoice->margin_amount_usd, 4) }}</td>
                                <td class="px-3 py-2 text-end font-medium">${{ number_format($invoice->billed_amount_usd, 4) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $invoice->status === 'paid',
                                        'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $invoice->status === 'pending',
                                        'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $invoice->status === 'overdue',
                                    ])>{{ ucfirst($invoice->status) }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-gray-400">No invoices yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Store Configs Tab --}}
    @if ($activeTab === 'stores')
        <x-filament::section heading="Store AI Configurations">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Store</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">AI Enabled</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Monthly Limit</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Custom Margin</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Notes</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($storeConfigs as $config)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-2 font-medium">{{ $config->store?->business_name ?? $config->store_id }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if ($config->is_ai_enabled)
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Enabled</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-end">{{ $config->monthly_limit_usd ? '$'.number_format($config->monthly_limit_usd, 2) : 'No limit' }}</td>
                                <td class="px-3 py-2 text-end">{{ $config->custom_margin_percentage ? $config->custom_margin_percentage.'%' : 'Default' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->notes ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $config->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-gray-400">No store configurations yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Settings Tab --}}
    @if ($activeTab === 'settings')
        <x-filament::section heading="Billing Settings">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Key</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settings as $key => $value)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-mono text-xs">{{ $key }}</td>
                                <td class="px-3 py-2">{{ $value }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-3 py-8 text-center text-gray-400">No settings configured</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
