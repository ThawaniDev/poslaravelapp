<x-filament-panels::page>
    {{-- Tab Navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
        @foreach (['overview' => __('ai.overview'), 'invoices' => __('ai.invoices'), 'stores' => __('ai.store_configs'), 'settings' => __('ai.settings')] as $tab => $label)
            <button
                wire:click="setTab('{{ $tab }}')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                    'border-primary-500 text-primary-600 dark:text-primary-400' => $activeTab === $tab,
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== $tab,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'overview')
        @include('filament.pages.wameed-ai-billing._tab-overview')
    @endif

    @if ($activeTab === 'invoices')
        @include('filament.pages.wameed-ai-billing._tab-invoices')
    @endif

    @if ($activeTab === 'stores')
        @include('filament.pages.wameed-ai-billing._tab-stores')
    @endif

    @if ($activeTab === 'settings')
        @include('filament.pages.wameed-ai-billing._tab-settings')
    @endif
</x-filament-panels::page>
