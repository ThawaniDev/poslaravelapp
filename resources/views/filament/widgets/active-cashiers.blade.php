<x-filament-widgets::widget>
    <x-filament::section :heading="__('owner_dashboard.filament.active_cashiers')">
        @if(empty($this->getData()['cashiers']))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('owner_dashboard.filament.no_active_cashiers') }}
            </p>
        @else
            <div class="space-y-3">
                @foreach($this->getData()['cashiers'] as $cashier)
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $cashier['user_name'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $cashier['register_name'] ?? __('owner_dashboard.filament.no_register') }}
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                {{ __('owner_dashboard.filament.active') }}
                            </span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $cashier['transaction_count'] }} {{ __('owner_dashboard.filament.txns') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
