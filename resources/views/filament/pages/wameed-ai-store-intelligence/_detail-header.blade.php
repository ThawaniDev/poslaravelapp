{{-- Store Detail Header --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <button wire:click="clearStore" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        {{ __('ai.back_to_stores') }}
    </button>
    <span class="text-gray-300 dark:text-gray-600">|</span>
    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $detail['store']->name }}</h2>
    @if ($detail['store']->name_ar)
        <span class="text-sm text-gray-400">({{ $detail['store']->name_ar }})</span>
    @endif
    @if ($detail['billingConfig'])
        @if ($detail['billingConfig']->is_ai_enabled)
            <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.ai_enabled') }}</span>
        @else
            <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.ai_disabled') }}</span>
        @endif
    @endif
    <div class="ms-auto">
        <select wire:model.live="dateRange" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
            <option value="7">{{ __('ai.last_7_days') }}</option>
            <option value="14">{{ __('ai.last_14_days') }}</option>
            <option value="30">{{ __('ai.last_30_days') }}</option>
            <option value="60">{{ __('ai.last_60_days') }}</option>
            <option value="90">{{ __('ai.last_90_days') }}</option>
            <option value="365">{{ __('ai.last_year') }}</option>
        </select>
    </div>
</div>

{{-- Tab Navigation --}}
<div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
    @foreach (['overview' => __('ai.overview'), 'features' => __('ai.features'), 'billing' => __('ai.billing'), 'trends' => __('ai.trends'), 'chats' => __('ai.chats'), 'logs' => __('ai.logs')] as $tab => $label)
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
