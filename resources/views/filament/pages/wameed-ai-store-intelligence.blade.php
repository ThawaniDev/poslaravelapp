<x-filament-panels::page>
    @if (! $selectedStoreId)
        @include('filament.pages.wameed-ai-store-intelligence._store-list')
    @else
        @include('filament.pages.wameed-ai-store-intelligence._detail-header')

        @if ($activeTab === 'overview')
            @include('filament.pages.wameed-ai-store-intelligence._tab-overview')
        @endif

        @if ($activeTab === 'features' && isset($features))
            @include('filament.pages.wameed-ai-store-intelligence._tab-features')
        @endif

        @if ($activeTab === 'billing' && isset($billing))
            @include('filament.pages.wameed-ai-store-intelligence._tab-billing')
        @endif

        @if ($activeTab === 'trends' && isset($trends))
            @include('filament.pages.wameed-ai-store-intelligence._tab-trends')
        @endif

        @if ($activeTab === 'chats' && isset($chatData))
            @include('filament.pages.wameed-ai-store-intelligence._tab-chats')
        @endif

        @if ($activeTab === 'logs' && isset($logData))
            @include('filament.pages.wameed-ai-store-intelligence._tab-logs')
        @endif
    @endif
</x-filament-panels::page>
