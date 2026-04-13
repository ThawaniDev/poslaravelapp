<x-filament-panels::page>
    {{-- Store Selector + Actions --}}
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.select_store') }}</label>
                <select wire:model.live="selectedStore" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('thawani.select_store') }}...</option>
                    @foreach($stores as $config)
                        <option value="{{ $config->store_id }}">{{ $config->store?->name ?? $config->thawani_store_id }}</option>
                    @endforeach
                </select>
            </div>
            @if($selectedStore)
            <div class="flex gap-2">
                <x-filament::button wire:click="pushProducts" color="primary" icon="heroicon-o-arrow-up-tray">
                    {{ __('thawani.push_to_thawani') }}
                </x-filament::button>
                <x-filament::button wire:click="pullProducts" color="info" icon="heroicon-o-arrow-down-tray">
                    {{ __('thawani.pull_from_thawani') }}
                </x-filament::button>
            </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Product Mappings Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.product_mappings') }} ({{ $mappings->count() }})</x-slot>
        @if($mappings->isEmpty())
            <p class="text-center text-gray-500 py-8">
                @if($selectedStore)
                    {{ __('thawani.no_product_mappings') }}
                @else
                    {{ __('thawani.select_store_first') }}
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-start">{{ __('thawani.wameed_product') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.sku') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.thawani_product_id') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.sell_price') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.online_price') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.published') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.last_synced') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $mapping)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3">
                                <div class="flex items-center gap-2">
                                    @if($mapping->product?->image_url)
                                        <img src="{{ $mapping->product->image_url }}" class="h-8 w-8 rounded object-cover" alt="">
                                    @endif
                                    <div>
                                        <p class="font-medium">{{ $mapping->product?->name ?? '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ $mapping->product?->name_ar ?? '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2 px-3 text-center"><code>{{ $mapping->product?->sku ?? '-' }}</code></td>
                            <td class="py-2 px-3 text-center"><code>{{ $mapping->thawani_product_id }}</code></td>
                            <td class="py-2 px-3 text-center">{{ number_format($mapping->product?->sell_price ?? 0, 2) }}</td>
                            <td class="py-2 px-3 text-center">{{ $mapping->online_price ? number_format($mapping->online_price, 2) : '-' }}</td>
                            <td class="py-2 px-3 text-center">
                                @if($mapping->is_published)
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-600 dark:bg-success-400/10 dark:text-success-400">{{ __('thawani.yes') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-600 dark:bg-danger-400/10 dark:text-danger-400">{{ __('thawani.no') }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-center">{{ $mapping->last_synced_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
