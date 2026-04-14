<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
                <div class="text-sm text-gray-500">{{ __('thawani.total_logs') }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ number_format($stats['success']) }}</div>
                <div class="text-sm text-gray-500">{{ __('thawani.successful') }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600">{{ number_format($stats['failed']) }}</div>
                <div class="text-sm text-gray-500">{{ __('thawani.failed') }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($stats['today']) }}</div>
                <div class="text-sm text-gray-500">{{ __('thawani.today') }}</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Filters --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.filters') }}</x-slot>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.store') }}</label>
                <select wire:model.live="selectedStore" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('thawani.all_stores') }}</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->store_id }}">{{ $store->store?->name ?? $store->thawani_store_id ?? $store->store_id }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.entity_type') }}</label>
                <select wire:model.live="filterEntityType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('thawani.all') }}</option>
                    <option value="product">{{ __('thawani.product') }}</option>
                    <option value="category">{{ __('thawani.category') }}</option>
                    <option value="connection">{{ __('thawani.connection') }}</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.status') }}</label>
                <select wire:model.live="filterStatus" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('thawani.all') }}</option>
                    <option value="success">{{ __('thawani.success') }}</option>
                    <option value="failed">{{ __('thawani.failed') }}</option>
                    <option value="pending">{{ __('thawani.pending') }}</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.direction') }}</label>
                <select wire:model.live="filterDirection" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('thawani.all') }}</option>
                    <option value="outgoing">{{ __('thawani.outgoing') }} (↑ Push)</option>
                    <option value="incoming">{{ __('thawani.incoming') }} (↓ Pull)</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.from_date') }}</label>
                <input type="date" wire:model.live="filterDateFrom" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.to_date') }}</label>
                <input type="date" wire:model.live="filterDateTo" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>
        <div class="mt-3">
            <x-filament::button wire:click="clearFilters" color="gray" size="sm">
                {{ __('thawani.clear_filters') }}
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Logs Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.sync_logs') }}</x-slot>
        @if($logs->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('thawani.no_sync_logs') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-start">{{ __('thawani.date') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.entity_type') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.entity_id') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.action') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.direction') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.status') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.error_message') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr class="border-b dark:border-gray-700" x-data="{ open: false }">
                            <td class="py-2 px-3 text-xs text-gray-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="py-2 px-3">
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">{{ $log->entity_type }}</span>
                            </td>
                            <td class="py-2 px-3 font-mono text-xs">{{ Str::limit($log->entity_id, 12) }}</td>
                            <td class="py-2 px-3 text-center text-xs">{{ $log->action }}</td>
                            <td class="py-2 px-3 text-center">
                                @if($log->direction === 'outgoing')
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-600 dark:bg-blue-400/10 dark:text-blue-400">↑ {{ __('thawani.outgoing') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-1 text-xs font-medium text-purple-600 dark:bg-purple-400/10 dark:text-purple-400">↓ {{ __('thawani.incoming') }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-center">
                                @if($log->status === 'success')
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-600 dark:bg-success-400/10 dark:text-success-400">{{ __('thawani.success') }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-600 dark:bg-danger-400/10 dark:text-danger-400">{{ __('thawani.failed') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-1 text-xs font-medium text-warning-600 dark:bg-warning-400/10 dark:text-warning-400">{{ $log->status }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-xs text-danger-600">{{ Str::limit($log->error_message, 50) }}</td>
                            <td class="py-2 px-3 text-center">
                                <button @click="open = !open" class="text-primary-600 hover:text-primary-800">
                                    <x-heroicon-s-eye class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        {{-- Expandable Detail Row --}}
                        <tr x-show="open" x-transition class="bg-gray-50 dark:bg-gray-800">
                            <td colspan="8" class="p-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @if($log->request_data)
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('thawani.request_data') }}</h4>
                                        <pre class="rounded-lg bg-gray-100 dark:bg-gray-900 p-3 text-xs font-mono overflow-auto max-h-48">{{ json_encode($log->request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    @endif
                                    @if($log->response_data)
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('thawani.response_data') }}</h4>
                                        <pre class="rounded-lg bg-gray-100 dark:bg-gray-900 p-3 text-xs font-mono overflow-auto max-h-48">{{ json_encode($log->response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    @endif
                                    @if($log->error_message)
                                    <div class="md:col-span-2">
                                        <h4 class="text-sm font-medium text-danger-600 mb-2">{{ __('thawani.error_message') }}</h4>
                                        <p class="text-sm text-danger-600 bg-danger-50 dark:bg-danger-400/10 rounded-lg p-3">{{ $log->error_message }}</p>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
