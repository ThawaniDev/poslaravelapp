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
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.overdue') }}</p>
                    <p class="text-2xl font-bold text-danger-600">{{ number_format($overdueInvoices) }}</p>
                </div>
            </x-filament::section>
        </div>
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
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.overdue') }}</p>
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
