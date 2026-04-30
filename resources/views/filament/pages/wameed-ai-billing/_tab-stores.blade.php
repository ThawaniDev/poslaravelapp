        <x-filament::section heading="{{ __('ai.store_ai_configurations') }}">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.ai_enabled') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.monthly_limit') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.custom_margin') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('zatca.notes') }}</th>
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
                                    <td class="px-3 py-2 font-medium">{{ $config->store?->name ?? ($config->organization?->name ? $config->organization->name . ' (' . __('ai.org_level') . ')' : ($config->store_id ?? __('ai.org_level'))) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <input wire:model="editConfigAiEnabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigMonthlyLimit" type="number" step="0.01" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm text-end" placeholder="{{ __('ai.no_limit_placeholder') }}">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigCustomMargin" type="number" step="0.1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm text-end" placeholder="{{ __('ai.default') }}">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input wire:model="editConfigNotes" type="text" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('zatca.notes') }}">
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $config->updated_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <x-filament::button wire:click="saveStoreConfig" size="xs" color="success">{{ __('ai.save') }}</x-filament::button>
                                            <x-filament::button wire:click="cancelEditConfig" size="xs" color="gray">{{ __('ai.cancel') }}</x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                {{-- Display Row --}}
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 font-medium">{{ $config->store?->name ?? ($config->organization?->name ? $config->organization->name . ' (' . __('ai.org_level') . ')' : ($config->store_id ?? __('ai.org_level'))) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($config->is_ai_enabled)
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.enabled') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.ai_disabled') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-end">{{ $config->monthly_limit_usd > 0 ? '$'.number_format($config->monthly_limit_usd, 2) : __('ai.no_limit') }}</td>
                                    <td class="px-3 py-2 text-end">{{ $config->custom_margin_percentage !== null ? $config->custom_margin_percentage.'%' : __('ai.default') }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->notes ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $config->updated_at?->diffForHumans() }}</td>
                                    @if ($canManage)
                                        <td class="px-3 py-2 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button wire:click="editStoreConfig('{{ $config->id }}')" class="text-xs text-primary-600 hover:text-primary-800 font-medium">{{ __('ai.edit') }}</button>
                                                <span class="text-gray-300">|</span>
                                                <button wire:click="toggleStoreAI('{{ $config->id }}')" class="text-xs {{ $config->is_ai_enabled ? 'text-danger-600 hover:text-danger-800' : 'text-success-600 hover:text-success-800' }} font-medium">
                                                    {{ $config->is_ai_enabled ? __('ai.disable') : __('ai.enable') }}
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
