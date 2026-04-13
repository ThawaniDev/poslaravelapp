<x-filament-panels::page>
    {{-- Form Section --}}
    <x-filament::section>
        <x-slot name="heading">{{ $editingId ? __('thawani.edit_column_mapping') : __('thawani.create_column_mapping') }}</x-slot>
        <form wire:submit="saveMapping">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.entity_type') }}</label>
                    <select wire:model="entityType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="product">{{ __('thawani.product') }}</option>
                        <option value="category">{{ __('thawani.category') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.thawani_field') }}</label>
                    <input type="text" wire:model="thawaniField" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="name, price, image..." required>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.wameed_field') }}</label>
                    <input type="text" wire:model="wameedField" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="name, sell_price, image_url..." required>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.transform_type') }}</label>
                    <select wire:model="transformType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="direct">{{ __('thawani.direct') }}</option>
                        <option value="json_extract">{{ __('thawani.json_extract') }}</option>
                        <option value="multiply">{{ __('thawani.multiply') }}</option>
                        <option value="map_value">{{ __('thawani.map_value') }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('thawani.transform_config') }}</label>
                    <textarea wire:model="transformConfig" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono text-xs" placeholder='{"locale": "en"}'></textarea>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <x-filament::button type="submit" color="primary">
                    {{ $editingId ? __('thawani.update') : __('thawani.save') }}
                </x-filament::button>
                @if($editingId)
                    <x-filament::button wire:click="resetForm" color="gray">
                        {{ __('thawani.cancel') }}
                    </x-filament::button>
                @endif
                <x-filament::button wire:click="seedDefaults" color="warning" class="ml-auto">
                    {{ __('thawani.seed_defaults') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Mappings Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('thawani.column_mappings') }} ({{ $mappings->count() }})</x-slot>
        @if($mappings->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('thawani.no_column_mappings') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-start">{{ __('thawani.entity_type') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.thawani_field') }}</th>
                            <th class="py-2 px-3 text-center">→</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.wameed_field') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.transform_type') }}</th>
                            <th class="py-2 px-3 text-start">{{ __('thawani.transform_config') }}</th>
                            <th class="py-2 px-3 text-center">{{ __('thawani.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $mapping)
                        <tr class="border-b dark:border-gray-700 {{ $editingId === $mapping->id ? 'bg-primary-50 dark:bg-primary-400/10' : '' }}">
                            <td class="py-2 px-3">
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">{{ $mapping->entity_type }}</span>
                            </td>
                            <td class="py-2 px-3 font-mono text-xs">{{ $mapping->thawani_field }}</td>
                            <td class="py-2 px-3 text-center text-gray-400">→</td>
                            <td class="py-2 px-3 font-mono text-xs">{{ $mapping->wameed_field }}</td>
                            <td class="py-2 px-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400">{{ $mapping->transform_type }}</span>
                            </td>
                            <td class="py-2 px-3 font-mono text-xs">{{ $mapping->transform_config ? json_encode($mapping->transform_config) : '-' }}</td>
                            <td class="py-2 px-3 text-center">
                                <div class="flex justify-center gap-1">
                                    <button wire:click="editMapping('{{ $mapping->id }}')" class="text-primary-600 hover:text-primary-800">
                                        <x-heroicon-s-pencil-square class="h-4 w-4" />
                                    </button>
                                    <button wire:click="deleteMapping('{{ $mapping->id }}')" wire:confirm="{{ __('thawani.confirm_delete') }}" class="text-danger-600 hover:text-danger-800">
                                        <x-heroicon-s-trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
