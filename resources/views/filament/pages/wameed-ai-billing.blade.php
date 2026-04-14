<x-filament-panels::page>
    {{-- Tab Navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
        @foreach (['overview' => __(\'ai.overview\'), 'invoices' => __(\'ai.invoices\'), 'stores' => __(\'ai.store_configs\'), 'settings' => __(\'ai.settings\')] as $tab => $label)
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
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_revenue_billed') }}</p>
                    <p class="text-3xl font-bold text-success-600">${{ number_format($totalRevenue, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.raw_cost_openai') }}</p>
                    <p class="text-3xl font-bold text-warning-600">${{ number_format($totalRawCost, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.platform_margin') }}</p>
                    <p class="text-3xl font-bold text-primary-600">${{ number_format($totalMargin, 2) }}</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mt-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.pending_revenue') }}</p>
                    <p class="text-2xl font-bold text-warning-600">${{ number_format($pendingRevenue, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.this_month_billed') }}</p>
                    <p class="text-2xl font-bold text-primary-600">${{ number_format($currentMonthRevenue, 2) }}</p>
                    <p class="text-xs text-gray-400">{{ __('ai.raw_label') }}\${{ number_format($currentMonthRawCost, 2) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.ai_enabled_stores') }}</p>
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
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_invoices') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($totalInvoices) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.paid') }}</p>
                    <p class="text-2xl font-bold text-success-600">{{ number_format($paidInvoices) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.pending') }}</p>
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

        @if ($canManage)
            <div class="mt-4">
                <x-filament::button wire:click="generateInvoices" color="primary" icon="heroicon-o-document-plus">
                    {{ __('ai.generate_last_month_invoices') }}
                </x-filament::button>
            </div>
        @endif
    @endif

    {{-- Invoices Tab --}}
    @if ($activeTab === 'invoices')
        <x-filament::section heading="Invoices">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.invoice_number') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.period') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_percentage') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_dollar') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.due_date') }}</th>
                            @if ($canManage)
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-2 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                                <td class="px-3 py-2">{{ $invoice->store?->name ?? '—' }}</td>
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
                                @if ($canManage)
                                    <td class="px-3 py-2 text-center">
                                        @if ($invoice->status === 'pending')
                                            <div class="flex items-center justify-center gap-1">
                                                <button wire:click="startMarkPaid('{{ $invoice->id }}')" class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __('ai.pay') }}</button>
                                                <span class="text-gray-300">|</span>
                                                <button wire:click="markInvoiceOverdue('{{ $invoice->id }}')" class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __('ai.overdue') }}</button>
                                            </div>
                                        @elseif ($invoice->status === 'overdue')
                                            <button wire:click="startMarkPaid('{{ $invoice->id }}')" class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __('ai.mark_paid') }}</button>
                                        @elseif ($invoice->status === 'paid')
                                            <span class="text-xs text-gray-400">{{ $invoice->paid_at?->format('M d') }}</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>

                            {{-- Mark Paid Form --}}
                            @if ($markingInvoiceId === $invoice->id)
                                <tr class="bg-success-50 dark:bg-success-500/5">
                                    <td colspan="{{ $canManage ? 11 : 10 }}" class="px-3 py-3">
                                        <div class="flex items-end gap-3">
                                            <div class="flex-1">
                                                <label class="text-xs font-medium text-gray-600">{{ __('ai.payment_reference') }}</label>
                                                <input wire:model="paymentReference" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="e.g. bank transfer #123">
                                            </div>
                                            <div class="flex-1">
                                                <label class="text-xs font-medium text-gray-600">{{ __('ai.notes') }}</label>
                                                <input wire:model="paymentNotes" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __(\'ai.optional_notes\') }}">
                                            </div>
                                            <x-filament::button wire:click="markInvoicePaid" size="sm" color="success">{{ __('ai.confirm_paid') }}</x-filament::button>
                                            <x-filament::button wire:click="cancelMarkPaid" size="sm" color="gray">{{ __('ai.cancel') }}</x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 11 : 10 }}" class="px-3 py-8 text-center text-gray-400">{{ __('ai.no_invoices_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Store Configs Tab --}}
    @if ($activeTab === 'stores')
        <x-filament::section heading="{{ __('ai.store_ai_configurations') }}">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.ai_enabled') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.monthly_limit') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.custom_margin') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Notes</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.updated') }}</th>
                            @if ($canManage)
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($storeConfigs as $config)
                            @if ($editingConfigId === $config->id)
                                {{-- Inline Edit Row --}}
                                <tr class="border-b border-primary-100 dark:border-primary-800 bg-primary-50 dark:bg-primary-500/5">
                                    <td class="px-3 py-2 font-medium">{{ $config->store?->name ?? $config->store_id }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <input wire:model="editConfigAiEnabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigMonthlyLimit" type="number" step="0.01" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm text-end" placeholder="0 = no limit">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigCustomMargin" type="number" step="0.1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm text-end" placeholder="Default">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigNotes" type="text" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="Notes...">
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $config->updated_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <x-filament::button wire:click="saveStoreConfig" size="xs" color="success">Save</x-filament::button>
                                            <x-filament::button wire:click="cancelEditConfig" size="xs" color="gray">Cancel</x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                {{-- Display Row --}}
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 font-medium">{{ $config->store?->name ?? $config->store_id }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($config->is_ai_enabled)
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.enabled') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.ai_disabled') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-end">{{ $config->monthly_limit_usd > 0 ? '$'.number_format($config->monthly_limit_usd, 2) : 'No limit' }}</td>
                                    <td class="px-3 py-2 text-end">{{ $config->custom_margin_percentage !== null ? $config->custom_margin_percentage.'%' : 'Default' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->notes ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $config->updated_at?->diffForHumans() }}</td>
                                    @if ($canManage)
                                        <td class="px-3 py-2 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button wire:click="editStoreConfig('{{ $config->id }}')" class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __('ai.edit') }}</button>
                                                <span class="text-gray-300">|</span>
                                                <button wire:click="toggleStoreAI('{{ $config->id }}')" class="text-xs {{ $config->is_ai_enabled ? 'text-danger-600 hover:text-danger-800' : 'text-success-600 hover:text-success-800' }} font-medium">
                                                    {{ $config->is_ai_enabled ? 'Disable' : 'Enable' }}
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 7 : 6 }}" class="px-3 py-8 text-center text-gray-400">{{ __('ai.no_store_configs') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Settings Tab --}}
    @if ($activeTab === 'settings')
        <x-filament::section heading="{{ __('ai.billing_settings') }}">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.key') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.value') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.description') }}</th>
                            @if ($canManage)
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settings as $key => $value)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-mono text-xs">{{ $key }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2">
                                        <input wire:model="editingSettings.{{ $key }}" type="text" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
                                    </td>
                                @else
                                    <td class="px-3 py-2">{{ $value }}</td>
                                @endif
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $settingDescriptions[$key] ?? '—' }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="saveSetting('{{ $key }}')" class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __('ai.save') }}</button>
                                            <span class="text-gray-300">|</span>
                                            <button wire:click="deleteSetting('{{ $key }}')" wire:confirm="Delete setting '{{ $key }}'?" class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __('ai.delete') }}</button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 4 : 3 }}" class="px-3 py-8 text-center text-gray-400">{{ __('ai.no_settings_configured') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">{{ __('ai.add_new_setting') }}</p>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-600">Key</label>
                            <input wire:model="newSettingKey" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="e.g. margin_percentage">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-600">Value</label>
                            <input wire:model="newSettingValue" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="e.g. 20">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-600">Description</label>
                            <input wire:model="newSettingDesc" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="Optional description">
                        </div>
                        <x-filament::button wire:click="addNewSetting" size="sm" color="primary" icon="heroicon-o-plus">Add</x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
