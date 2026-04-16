<x-filament-panels::page>
    {{-- Store Selector --}}
    <x-filament::section>
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <label for="store-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('thawani.select_store') }}
                </label>
                <select
                    id="store-select"
                    wire:model.live="selectedStoreId"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="">-- {{ __('thawani.select_store') }} --</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }} {{ $store->name_ar ? '('.$store->name_ar.')' : '' }}</option>
                    @endforeach
                </select>
            </div>

            @if($currentConfig)
                <div class="flex items-center gap-2 pt-6">
                    @if($currentConfig->is_connected)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-3 py-1 text-sm font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">
                            <span class="h-2 w-2 rounded-full bg-success-500"></span>
                            {{ __('thawani.connected') }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $currentConfig->connected_at?->diffForHumans() }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-3 py-1 text-sm font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                            <span class="h-2 w-2 rounded-full bg-danger-500"></span>
                            {{ __('thawani.not_connected') }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    @if($selectedStoreId)
        {{-- Connection Info --}}
        @if($currentConfig && $currentConfig->is_connected)
            <x-filament::section>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.thawani_store_id') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentConfig->thawani_store_id ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.marketplace_url') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentConfig->marketplace_url ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('thawani.api_key') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentConfig->api_key ? Str::mask($currentConfig->api_key, '*', 4) : '-' }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Config Form --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                @if($currentConfig && $currentConfig->is_connected)
                    <x-filament::button type="button" wire:click="disconnect" color="danger" outlined>
                        {{ __('thawani.disconnect') }}
                    </x-filament::button>
                @endif

                <x-filament::button type="button" wire:click="testConnection" color="gray">
                    {{ __('thawani.test_connection') }}
                </x-filament::button>

                <x-filament::button type="submit">
                    {{ __('thawani.save_config') }}
                </x-filament::button>
            </div>
        </form>
    @else
        <x-filament::section>
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-building-storefront class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-2 text-lg font-medium">{{ __('thawani.select_store_prompt') }}</p>
                <p class="text-sm">{{ __('thawani.select_store_prompt_description') }}</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
