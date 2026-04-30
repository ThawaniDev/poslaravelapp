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
                                            <button wire:click="deleteSetting('{{ $key }}')" wire:confirm="{{ __('ai.confirm_delete_setting', ['key' => $key]) }}" class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __('ai.delete') }}</button>
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
                            <label class="text-xs font-medium text-gray-600">{{ __('ai.key') }}</label>
                            <input wire:model="newSettingKey" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('ai.placeholder_setting_key') }}">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-600">{{ __('ai.value') }}</label>
                            <input wire:model="newSettingValue" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('ai.placeholder_setting_value') }}">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-medium text-gray-600">{{ __('ai.description') }}</label>
                            <input wire:model="newSettingDesc" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('ai.placeholder_setting_desc') }}">
                        </div>
                        <x-filament::button wire:click="addNewSetting" size="sm" color="primary" icon="heroicon-o-plus">{{ __('ai.add') }}</x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
