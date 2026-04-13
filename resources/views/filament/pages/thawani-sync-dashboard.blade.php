<x-filament-panels::page>
    {{-- KPI Cards --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.connected_stores') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ $connectedStores->count() }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.mapped_products') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ $totalMappedProducts }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.mapped_categories') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ $totalMappedCategories }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.pending_queue') }}</p>
                <p class="text-3xl font-bold {{ $pendingQueue > 0 ? 'text-warning-600' : 'text-success-600' }}">{{ $pendingQueue }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.failed_queue') }}</p>
                <p class="text-3xl font-bold {{ $failedQueue > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $failedQueue }}</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Today's Sync Stats --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.syncs_today') }}</p>
                <p class="text-2xl font-bold text-primary-600">{{ $syncLogsToday }}</p>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.successful_today') }}</p>
                <p class="text-2xl font-bold text-success-600">{{ $successSyncsToday }}</p>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.failed_today') }}</p>
                <p class="text-2xl font-bold {{ $failedSyncsToday > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $failedSyncsToday }}</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Connected Stores --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.connected_stores') }}</x-slot>
        @if(count($storeStats) === 0)
            <p class="text-center text-gray-500 py-8">{{ __('thawani.no_connected_stores') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-start">{{ __('thawani.store_name') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.thawani_store_id') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.mapped_products') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.mapped_categories') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.pending_queue') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.connected_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($storeStats as $stat)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3">{{ $stat['config']->store?->name ?? '-' }}</td>
                            <td class="py-2 px-3 text-center"><code>{{ $stat['config']->thawani_store_id }}</code></td>
                            <td class="py-2 px-3 text-center">{{ $stat['products'] }}</td>
                            <td class="py-2 px-3 text-center">{{ $stat['categories'] }}</td>
                            <td class="py-2 px-3 text-center">
                                @if($stat['pending'] > 0)
                                    <span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-1 text-xs font-medium text-warning-600 dark:bg-warning-400/10 dark:text-warning-400">{{ $stat['pending'] }}</span>
                                @else
                                    <span class="text-success-600">0</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-center">{{ $stat['config']->connected_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Recent Sync Logs --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.recent_sync_logs') }}</x-slot>
        @if($recentLogs->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('thawani.no_sync_logs') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-start">{{ __('thawani.time') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.store') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.entity_type') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.action') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.direction') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.status') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLogs as $log)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3">{{ $log->created_at->format('H:i:s') }}</td>
                            <td class="py-2 px-3">{{ $log->store?->name ?? '-' }}</td>
                            <td class="py-2 px-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">{{ $log->entity_type }}</span>
                            </td>
                            <td class="py-2 px-3 text-center">{{ $log->action }}</td>
                            <td class="py-2 px-3 text-center">
                                @if($log->direction === 'incoming')
                                    <span class="inline-flex items-center rounded-full bg-info-50 px-2 py-1 text-xs font-medium text-info-600 dark:bg-info-400/10 dark:text-info-400">{{ __('thawani.incoming') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-1 text-xs font-medium text-warning-600 dark:bg-warning-400/10 dark:text-warning-400">{{ __('thawani.outgoing') }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-center">
                                @if($log->status === 'success')
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-600 dark:bg-success-400/10 dark:text-success-400">{{ __('thawani.success') }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-600 dark:bg-danger-400/10 dark:text-danger-400">{{ __('thawani.failed') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400">{{ __('thawani.pending') }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-danger-600">{{ Str::limit($log->error_message, 40) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Quick Navigation --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <a href="{{ \App\Filament\Pages\ThawaniProductMappings::getUrl() }}" class="block">
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-cube class="mx-auto h-8 w-8 text-primary-500" />
                    <p class="mt-2 font-medium">{{ __('thawani.product_mappings') }}</p>
                    <p class="text-xs text-gray-500">{{ __('thawani.product_mappings_desc') }}</p>
                </div>
            </x-filament::section>
        </a>
        <a href="{{ \App\Filament\Pages\ThawaniCategoryMappings::getUrl() }}" class="block">
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-tag class="mx-auto h-8 w-8 text-info-500" />
                    <p class="mt-2 font-medium">{{ __('thawani.category_mappings') }}</p>
                    <p class="text-xs text-gray-500">{{ __('thawani.category_mappings_desc') }}</p>
                </div>
            </x-filament::section>
        </a>
        <a href="{{ \App\Filament\Pages\ThawaniColumnMappings::getUrl() }}" class="block">
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-adjustments-horizontal class="mx-auto h-8 w-8 text-warning-500" />
                    <p class="mt-2 font-medium">{{ __('thawani.column_mappings') }}</p>
                    <p class="text-xs text-gray-500">{{ __('thawani.column_mappings_desc') }}</p>
                </div>
            </x-filament::section>
        </a>
        <a href="{{ \App\Filament\Pages\ThawaniSyncLogs::getUrl() }}" class="block">
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-document-text class="mx-auto h-8 w-8 text-gray-500" />
                    <p class="mt-2 font-medium">{{ __('thawani.sync_logs') }}</p>
                    <p class="text-xs text-gray-500">{{ __('thawani.sync_logs_desc') }}</p>
                </div>
            </x-filament::section>
        </a>
    </div>
</x-filament-panels::page>
