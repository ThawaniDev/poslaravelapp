<div
    x-data="{ open: $wire.entangle('open') }"
    class="relative"
>
    {{-- Trigger Button --}}
    <button
        wire:click="toggle"
        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:bg-gray-100 dark:hover:bg-white/5"
        :title="open ? '{{ __('nav.quick_nav_close') }}' : '{{ __('nav.quick_nav') }}'"
    >
        <x-filament::icon
            icon="heroicon-o-squares-2x2"
            class="h-5 w-5 text-gray-700 dark:text-gray-300"
        />
        <span class="hidden sm:inline text-gray-700 dark:text-gray-300">{{ __('nav.quick_nav_short') }}</span>
    </button>

    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false; $wire.close()"
        class="fixed inset-0 z-40 bg-black/30"
        style="display:none"
    ></div>

    {{-- Popup Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        x-on:keydown.escape.window="open = false; $wire.close()"
        class="fixed inset-x-4 top-16 z-50 mx-auto flex max-h-[calc(100vh-5rem)] max-w-4xl flex-col rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:inset-x-auto sm:start-auto sm:end-auto sm:w-[56rem]"
        style="display:none"
    >
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                <x-filament::icon icon="heroicon-o-squares-2x2" class="inline-block h-5 w-5 me-1 -mt-0.5" />
                {{ __('nav.quick_nav') }}
            </h3>
            <button
                x-on:click="open = false; $wire.close()"
                class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            >
                <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
            </button>
        </div>

        {{-- Body — scrollable grouped grid --}}
        <div class="min-h-0 flex-1 overflow-y-auto p-5 space-y-5">
            @foreach ($this->groups as $group)
                <div>
                    {{-- Group label --}}
                    <h4 class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ $group['label'] }}
                    </h4>

                    {{-- Grid items --}}
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                        @foreach ($group['items'] as $item)
                            <button
                                wire:click="navigateTo('{{ $item['url'] }}')"
                                @class([
                                    'group flex flex-col items-center gap-2 rounded-xl p-3 text-center transition',
                                    'bg-primary-50 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => $item['isActive'],
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => ! $item['isActive'],
                                ])
                            >
                                @if ($item['icon'])
                                    <x-filament::icon
                                        :icon="$item['icon']"
                                        @class([
                                            'h-6 w-6 transition',
                                            'text-primary-600 dark:text-primary-400' => $item['isActive'],
                                            'text-gray-400 group-hover:text-primary-500 dark:text-gray-500 dark:group-hover:text-primary-400' => ! $item['isActive'],
                                        ])
                                    />
                                @endif
                                <span @class([
                                    'text-xs font-medium leading-tight',
                                    'text-primary-700 dark:text-primary-300' => $item['isActive'],
                                    'text-gray-600 dark:text-gray-300' => ! $item['isActive'],
                                ])>
                                    {{ $item['label'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
