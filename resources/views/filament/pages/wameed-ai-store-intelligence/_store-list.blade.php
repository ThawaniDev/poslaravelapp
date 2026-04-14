{{-- Platform Summary KPIs --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
    <x-filament::section>
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.ai_active_stores') }}</p>
            <p class="text-3xl font-bold text-primary-600">{{ number_format($platformTotals['total_stores']) }}</p>
        </div>
    </x-filament::section>
    <x-filament::section>
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_requests') }}</p>
            <p class="text-3xl font-bold text-info-600">{{ number_format($platformTotals['total_requests']) }}</p>
            <p class="text-xs text-gray-400">Last {{ $dateRange }}d: {{ number_format($platformTotals['recent_requests']) }}</p>
        </div>
    </x-filament::section>
    <x-filament::section>
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_raw_cost') }}</p>
            <p class="text-3xl font-bold text-warning-600">${{ number_format($platformTotals['total_raw_cost'], 4) }}</p>
            <p class="text-xs text-gray-400">Last {{ $dateRange }}d: ${{ number_format($platformTotals['recent_raw_cost'], 4) }}</p>
        </div>
    </x-filament::section>
    <x-filament::section>
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_billed') }}</p>
            <p class="text-3xl font-bold text-success-600">${{ number_format($platformTotals['total_billed_cost'], 4) }}</p>
            <p class="text-xs text-gray-400">Last {{ $dateRange }}d: ${{ number_format($platformTotals['recent_billed_cost'], 4) }}</p>
        </div>
    </x-filament::section>
    <x-filament::section>
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.platform_margin') }}</p>
            <p class="text-3xl font-bold text-success-600">${{ number_format($platformTotals['total_margin'], 4) }}</p>
            <p class="text-xs text-gray-400">{{ number_format($platformTotals['total_chats']) }} {{ __('ai.chats') }} · {{ number_format($platformTotals['total_errors']) }} {{ __('ai.errors') }}</p>
        </div>
    </x-filament::section>
</div>

{{-- Search & Date Range --}}
<div class="flex items-center gap-3 mt-4">
    <div class="flex-1">
        <input
            type="text"
            wire:model.live.debounce.300ms="searchQuery"
            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
            placeholder="{{ __('ai.search_stores') }}"
        />
    </div>
    <select wire:model.live="dateRange" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
        <option value="7">{{ __('ai.last_7_days') }}</option>
        <option value="14">{{ __('ai.last_14_days') }}</option>
        <option value="30">{{ __('ai.last_30_days') }}</option>
        <option value="60">{{ __('ai.last_60_days') }}</option>
        <option value="90">{{ __('ai.last_90_days') }}</option>
        <option value="365">{{ __('ai.last_year') }}</option>
    </select>
</div>

{{-- Store Table --}}
<x-filament::section heading="{{ __('ai.stores_with_ai_activity') }}" class="mt-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.requests') }} ({{ $dateRange }}d)</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.all_time_requests') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.raw_cost') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.billed') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.tokens_label') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.chats') }}</th>
                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.errors') }}</th>
                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.last_activity') }}</th>
                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stores as $store)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer" wire:click="selectStore('{{ $store->id }}')">
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ $store->name }}</div>
                            @if ($store->name_ar)
                                <div class="text-xs text-gray-400">{{ $store->name_ar }}</div>
                            @endif
                            <div class="text-xs text-gray-400">{{ $store->slug }}</div>
                        </td>
                        <td class="px-3 py-2 text-end font-mono font-bold text-primary-600">{{ number_format($store->recent_requests) }}</td>
                        <td class="px-3 py-2 text-end font-mono text-gray-500">{{ number_format($store->total_requests) }}</td>
                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($store->total_raw_cost, 4) }}</td>
                        <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($store->total_billed_cost, 4) }}</td>
                        <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($store->total_billed_cost - $store->total_raw_cost, 4) }}</td>
                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($store->total_tokens_used) }}</td>
                        <td class="px-3 py-2 text-end">{{ number_format($store->total_chats) }}</td>
                        <td class="px-3 py-2 text-end">
                            @if ($store->error_count > 0)
                                <span class="text-danger-600 font-medium">{{ number_format($store->error_count) }}</span>
                            @else
                                <span class="text-gray-400">0</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">
                            {{ $store->last_ai_activity ? \Carbon\Carbon::parse($store->last_ai_activity)->diffForHumans() : __('ai.never') }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button wire:click="selectStore('{{ $store->id }}')" class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __('ai.view') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-8 text-center text-gray-400">{{ __('ai.no_ai_activity') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
