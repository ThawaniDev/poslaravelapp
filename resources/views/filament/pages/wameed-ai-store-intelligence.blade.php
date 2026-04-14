<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- STORE LIST VIEW (no store selected) --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if (! $selectedStoreId)

        {{-- Platform Summary KPIs --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.ai_active_stores') }}</p>
                    <p class="text-3xl font-bold text-primary-600">{{ number_format($platformTotals['total_stores']) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_requests') }}</p>
                    <p class="text-3xl font-bold text-info-600">{{ number_format($platformTotals['total_requests']) }}</p>
                    <p class="text-xs text-gray-400">Last {{ $dateRange }}d: {{ number_format($platformTotals['recent_requests']) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_raw_cost') }}</p>
                    <p class="text-3xl font-bold text-warning-600">${{ number_format($platformTotals['total_raw_cost'], 4) }}</p>
                    <p class="text-xs text-gray-400">Last {{ $dateRange }}d: ${{ number_format($platformTotals['recent_raw_cost'], 4) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_billed') }}</p>
                    <p class="text-3xl font-bold text-success-600">${{ number_format($platformTotals['total_billed_cost'], 4) }}</p>
                    <p class="text-xs text-gray-400">Last {{ $dateRange }}d: ${{ number_format($platformTotals['recent_billed_cost'], 4) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.platform_margin') }}</p>
                    <p class="text-3xl font-bold text-success-600">${{ number_format($platformTotals['total_margin'], 4) }}</p>
                    <p class="text-xs text-gray-400">{{ number_format($platformTotals['total_chats']) }} chats · {{ number_format($platformTotals['total_errors']) }} errors</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Search & Date Range --}}
        <div class="flex items-center gap-3 mt-4">
            <div class="flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchQuery"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                    placeholder="{{ __('ai.search_stores') }}"
                />
            </div>
            <select wire:model.live="dateRange" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                <option value="7">{{ __('ai.last_7_days') }}</option>
                <option value="14">{{ __('ai.last_14_days') }}</option>
                <option value="30">{{ __('ai.last_30_days') }}</option>
                <option value="60">{{ __('ai.last_60_days') }}</option>
                <option value="90">{{ __('ai.last_90_days') }}</option>
                <option value="365">{{ __('ai.last_year') }}</option>
            </select>
        </div>

        {{-- Store Table --}}
        <x-filament::section heading="{{ __('ai.stores_with_ai_activity') }}" class="mt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Requests ({{ $dateRange }}d)</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">All-Time Requests</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Margin</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Chats</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">Errors</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">Last Activity</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer" wire:click="selectStore('{{ $store->id }}')">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $store->name }}</div>
                                    @if ($store->name_ar)
                                        <div class="text-xs text-gray-400">{{ $store->name_ar }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400">{{ $store->slug }}</div>
                                </td>
                                <td class="px-3 py-2 text-end font-mono font-bold text-primary-600">{{ number_format($store->recent_requests) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-gray-500">{{ number_format($store->total_requests) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($store->total_raw_cost, 4) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($store->total_billed_cost, 4) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($store->total_billed_cost - $store->total_raw_cost, 4) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($store->total_tokens_used) }}</td>
                                <td class="px-3 py-2 text-end">{{ number_format($store->total_chats) }}</td>
                                <td class="px-3 py-2 text-end">
                                    @if ($store->error_count > 0)
                                        <span class="text-danger-600 font-medium">{{ number_format($store->error_count) }}</span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">
                                    {{ $store->last_ai_activity ? \Carbon\Carbon::parse($store->last_ai_activity)->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-3 py-2 text-center">
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
            @foreach (['overview' => __(\'ai.overview\'), 'features' => 'Features', 'billing' => 'Billing', 'trends' => 'Trends', 'chats' => 'Chats', 'logs' => 'Logs'] as $tab => $label)
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

        {{-- ══════════════ OVERVIEW TAB ══════════════ --}}
        @if ($activeTab === 'overview')
            {{-- Primary KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_requests') }}</p>
                        <p class="text-3xl font-bold text-primary-600">{{ number_format($detail['totalRequests']) }}</p>
                        <p class="text-xs text-gray-400">Today: {{ number_format($detail['todayRequests']) }} · Last {{ $dateRange }}d: {{ number_format($detail['recentRequests']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Raw Cost (All Time)</p>
                        <p class="text-3xl font-bold text-warning-600">${{ number_format($detail['totalRawCost'], 4) }}</p>
                        <p class="text-xs text-gray-400">Today: ${{ number_format($detail['todayRawCost'], 4) }} · Last {{ $dateRange }}d: ${{ number_format($detail['recentRawCost'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Billed Cost (All Time)</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($detail['totalBilledCost'], 4) }}</p>
                        <p class="text-xs text-gray-400">Today: ${{ number_format($detail['todayBilledCost'], 4) }} · Last {{ $dateRange }}d: ${{ number_format($detail['recentBilledCost'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.platform_margin') }}</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($detail['totalMargin'], 4) }}</p>
                        @php $marginPct = $detail['totalRawCost'] > 0 ? round($detail['totalMargin'] / $detail['totalRawCost'] * 100, 1) : 0; @endphp
                        <p class="text-xs text-gray-400">{{ $marginPct }}% markup</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Secondary KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5 mt-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Tokens</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($detail['totalTokens']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.avg_latency') }}</p>
                        <p class="text-2xl font-bold {{ $detail['avgLatency'] > 5000 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($detail['avgLatency']) }}ms</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.cache_hit_rate') }}</p>
                        <p class="text-2xl font-bold text-info-600">{{ $detail['cacheHitRate'] }}%</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Error Rate</p>
                        <p class="text-2xl font-bold {{ $detail['errorRate'] > 5 ? 'text-danger-600' : 'text-success-600' }}">{{ $detail['errorRate'] }}%</p>
                        <p class="text-xs text-gray-400">{{ number_format($detail['errorCount']) }} errors</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Chats</p>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($detail['totalChats']) }}</p>
                        <p class="text-xs text-gray-400">{{ number_format($detail['totalChatMessages']) }} messages</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Store Info Card --}}
            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                <x-filament::section heading="Store Information">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Store Name</span>
                            <p class="font-medium">{{ $detail['store']->name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Arabic Name</span>
                            <p class="font-medium">{{ $detail['store']->name_ar ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Slug</span>
                            <p class="font-mono text-xs">{{ $detail['store']->slug }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Business Type</span>
                            <p class="font-medium">{{ $detail['store']->business_type ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Store Active</span>
                            <p>
                                @if ($detail['store']->is_active)
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Store Created</span>
                            <p class="text-xs">{{ $detail['store']->created_at?->format('M d, Y') ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">First AI Use</span>
                            <p class="text-xs">{{ $detail['firstActivity'] ? \Carbon\Carbon::parse($detail['firstActivity'])->format('M d, Y H:i') : 'Never' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Last AI Use</span>
                            <p class="text-xs">{{ $detail['lastActivity'] ? \Carbon\Carbon::parse($detail['lastActivity'])->diffForHumans() . ' (' . \Carbon\Carbon::parse($detail['lastActivity'])->format('M d, Y H:i') . ')' : 'Never' }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Billing Configuration">
                    @if ($detail['billingConfig'])
                        @php $bc = $detail['billingConfig']; @endphp
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">AI Status</span>
                                <p>
                                    @if ($bc->is_ai_enabled)
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.enabled') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.ai_disabled') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Monthly Limit</span>
                                <p class="font-medium">{{ $bc->monthly_limit_usd > 0 ? '$' . number_format($bc->monthly_limit_usd, 2) : 'No limit' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Custom Margin %</span>
                                <p class="font-medium">{{ $bc->custom_margin_percentage !== null ? $bc->custom_margin_percentage . '%' : 'Platform default' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Disabled Reason</span>
                                <p class="text-xs">{{ $bc->disabled_reason ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Enabled At</span>
                                <p class="text-xs">{{ $bc->enabled_at ? \Carbon\Carbon::parse($bc->enabled_at)->format('M d, Y H:i') : '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Disabled At</span>
                                <p class="text-xs">{{ $bc->disabled_at ? \Carbon\Carbon::parse($bc->disabled_at)->format('M d, Y H:i') : '—' }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500 dark:text-gray-400">Notes</span>
                                <p class="text-xs">{{ $bc->notes ?? '—' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">No billing configuration set up for this store.</p>
                    @endif
                </x-filament::section>
            </div>
        @endif

        {{-- ══════════════ FEATURES TAB ══════════════ --}}
        @if ($activeTab === 'features' && isset($features))
            {{-- Models Used --}}
            <x-filament::section heading="Models Used (Last {{ $dateRange }} Days)">
                @if ($features['modelsUsed']->isEmpty())
                    <p class="text-sm text-gray-400">No model usage data.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Model</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['modelsUsed'] as $model)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $model->model_used ?? 'Unknown' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($model->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($model->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($model->raw_cost, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Feature Usage (Recent) --}}
            <x-filament::section heading="Feature Usage (Last {{ $dateRange }} Days)" class="mt-4">
                @if ($features['featureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">No feature usage data for this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Avg Latency</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Errors</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Cached</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Last Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['featureUsage'] as $feature)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                {{ $feature->feature_slug }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono font-bold">{{ number_format($feature->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($feature->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($feature->raw_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($feature->billed_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end text-xs {{ round($feature->avg_latency) > 5000 ? 'text-danger-600' : 'text-gray-500' }}">{{ number_format(round($feature->avg_latency)) }}ms</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($feature->error_count > 0)
                                                <span class="text-danger-600 font-medium">{{ $feature->error_count }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end text-xs text-gray-500">{{ number_format($feature->cached_count) }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $feature->last_used ? \Carbon\Carbon::parse($feature->last_used)->diffForHumans() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- All-Time Feature Usage --}}
            <x-filament::section heading="All-Time Feature Usage" class="mt-4">
                @if ($features['allTimeFeatureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">No all-time usage data.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Billed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['allTimeFeatureUsage'] as $feature)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                {{ $feature->feature_slug }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($feature->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($feature->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($feature->raw_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($feature->billed_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($feature->billed_cost - $feature->raw_cost, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Store Feature Configs --}}
            <x-filament::section heading="Store Feature Configurations" class="mt-4">
                @if ($features['featureConfigs']->isEmpty())
                    <p class="text-sm text-gray-400">No custom feature configurations for this store.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">Enabled</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Daily Limit</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.monthly_limit') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Custom Prompt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['featureConfigs'] as $config)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ $config->featureDefinition?->name ?? $config->ai_feature_definition_id }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($config->is_enabled)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Yes</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">No</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end">{{ $config->daily_limit ?? 'Default' }}</td>
                                        <td class="px-3 py-2 text-end">{{ $config->monthly_limit ?? 'Default' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->custom_prompt_override ? 'Yes (custom)' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- ══════════════ BILLING TAB ══════════════ --}}
        @if ($activeTab === 'billing' && isset($billing))
            {{-- Billing KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Invoiced</p>
                        <p class="text-3xl font-bold text-primary-600">${{ number_format($billing['totalInvoiced'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($billing['totalPaid'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.pending') }}</p>
                        <p class="text-3xl font-bold text-warning-600">${{ number_format($billing['totalPending'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
                        <p class="text-3xl font-bold text-danger-600">${{ number_format($billing['totalOverdue'], 4) }}</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Current Month Running --}}
            <x-filament::section heading="Current Month (Running)" class="mt-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Requests</p>
                        <p class="text-2xl font-bold text-primary-600">{{ number_format($billing['currentMonthRequests']) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Raw Cost</p>
                        <p class="text-2xl font-bold text-warning-600">${{ number_format($billing['currentMonthRawCost'], 4) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Billed Cost</p>
                        <p class="text-2xl font-bold text-success-600">${{ number_format($billing['currentMonthBilledCost'], 4) }}</p>
                    </div>
                </div>
                @if ($billing['billingConfig'] && $billing['billingConfig']->monthly_limit_usd > 0)
                    @php
                        $limit = $billing['billingConfig']->monthly_limit_usd;
                        $usedPct = min(100, round($billing['currentMonthBilledCost'] / $limit * 100, 1));
                    @endphp
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>Monthly Limit: ${{ number_format($limit, 2) }}</span>
                            <span>{{ $usedPct }}% used</span>
                        </div>
                        <div class="h-3 rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-3 rounded-full {{ $usedPct >= 90 ? 'bg-danger-500' : ($usedPct >= 70 ? 'bg-warning-500' : 'bg-success-500') }}" style="width: {{ $usedPct }}%"></div>
                        </div>
                    </div>
                @endif
            </x-filament::section>

            {{-- Billing Configuration --}}
            <x-filament::section heading="Billing Configuration" class="mt-4">
                @if ($billing['billingConfig'])
                    @php $bc = $billing['billingConfig']; @endphp
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                        <div>
                            <span class="text-gray-500">AI Enabled</span>
                            <p class="font-medium">{{ $bc->is_ai_enabled ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Monthly Limit</span>
                            <p class="font-medium">{{ $bc->monthly_limit_usd > 0 ? '$' . number_format($bc->monthly_limit_usd, 2) : 'No limit' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Custom Margin</span>
                            <p class="font-medium">{{ $bc->custom_margin_percentage !== null ? $bc->custom_margin_percentage . '%' : 'Platform default' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Notes</span>
                            <p class="text-xs">{{ $bc->notes ?? '—' }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400">No billing configuration.</p>
                @endif
            </x-filament::section>

            {{-- Invoice History --}}
            <x-filament::section heading="Invoice History" class="mt-4">
                @if ($billing['invoices']->isEmpty())
                    <p class="text-sm text-gray-400">No invoices yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.invoice_number') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.period') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_percentage') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_dollar') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.due_date') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Paid At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['invoices'] as $invoice)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                                        <td class="px-3 py-2">{{ $invoice->year }}-{{ str_pad($invoice->month, 2, '0', STR_PAD_LEFT) }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($invoice->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($invoice->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($invoice->raw_cost_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($invoice->margin_percentage, 1) }}%</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($invoice->margin_amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-medium">${{ number_format($invoice->billed_amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $invoice->status === 'paid',
                                                'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $invoice->status === 'pending',
                                                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $invoice->status === 'overdue',
                                                'bg-gray-50 text-gray-700 dark:bg-gray-500/10 dark:text-gray-400' => ! in_array($invoice->status, ['paid', 'pending', 'overdue']),
                                            ])>{{ ucfirst($invoice->status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->paid_at?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Latest Invoice Items --}}
            @if ($billing['latestInvoice'])
                <x-filament::section heading="Latest Invoice Breakdown: {{ $billing['latestInvoice']->invoice_number }}" class="mt-4">
                    @if ($billing['latestInvoiceItems']->isEmpty())
                        <p class="text-sm text-gray-400">No line items.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">Feature (AR)</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                        <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($billing['latestInvoiceItems'] as $item)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="px-3 py-2">{{ $item->feature_name ?? $item->feature_slug }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">{{ $item->feature_name_ar ?? '—' }}</td>
                                            <td class="px-3 py-2 text-end font-mono">{{ number_format($item->request_count) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($item->total_tokens) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($item->raw_cost_usd, 4) }}</td>
                                            <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($item->billed_cost_usd, 4) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            {{-- Payment History --}}
            <x-filament::section heading="Payment History" class="mt-4">
                @if ($billing['payments']->isEmpty())
                    <p class="text-sm text-gray-400">No payments recorded.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Date</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Amount</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Method</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Reference</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['payments'] as $payment)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 text-xs">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-medium text-success-600">${{ number_format($payment->amount_usd, 4) }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $payment->payment_method ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs font-mono">{{ $payment->reference ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $payment->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Monthly Summary Trend --}}
            <x-filament::section heading="Monthly Usage Trend" class="mt-4">
                @if ($billing['monthlySummaries']->isEmpty())
                    <p class="text-sm text-gray-400">No monthly summaries available.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Month</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Cached</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Failed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billing['monthlySummaries'] as $summary)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ $summary->month }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($summary->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($summary->cached_requests) }}</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($summary->failed_requests > 0)
                                                <span class="text-danger-600 font-medium">{{ number_format($summary->failed_requests) }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format(($summary->total_input_tokens ?? 0) + ($summary->total_output_tokens ?? 0)) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($summary->total_estimated_cost_usd, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- ══════════════ TRENDS TAB ══════════════ --}}
        @if ($activeTab === 'trends' && isset($trends))
            {{-- Daily Usage Chart (Bar viz) --}}
            <x-filament::section heading="Daily Usage (Last {{ $dateRange }} Days)">
                @if ($trends['dailyUsage']->isEmpty())
                    <p class="text-sm text-gray-400">No daily usage data.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($trends['dailyUsage'] as $day)
                            @php
                                $maxReqs = $trends['dailyUsage']->max('requests') ?: 1;
                                $pct = ($day->requests / $maxReqs) * 100;
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</span>
                                <div class="flex-1">
                                    <div class="h-5 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-5 rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($day->requests) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-gray-400">${{ number_format($day->raw_cost, 4) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-success-600">${{ number_format($day->billed_cost, 4) }}</span>
                                <span class="w-14 text-right text-xs font-mono {{ round($day->avg_latency) > 5000 ? 'text-danger-500' : 'text-gray-400' }}">{{ number_format(round($day->avg_latency)) }}ms</span>
                                <span class="w-8 text-right text-xs font-mono {{ $day->errors > 0 ? 'text-danger-500' : 'text-gray-400' }}">{{ $day->errors }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400">
                        <span class="w-20">Date</span>
                        <span class="flex-1">Volume</span>
                        <span class="w-14 text-right">Reqs</span>
                        <span class="w-20 text-right">Raw $</span>
                        <span class="w-20 text-right">Billed $</span>
                        <span class="w-14 text-right">Latency</span>
                        <span class="w-8 text-right">Err</span>
                    </div>
                @endif
            </x-filament::section>

            {{-- Hourly Breakdown Today --}}
            <x-filament::section heading="Today's Hourly Breakdown" class="mt-4">
                @if ($trends['hourlyToday']->isEmpty())
                    <p class="text-sm text-gray-400">No requests today.</p>
                @else
                    <div class="space-y-1">
                        @foreach ($trends['hourlyToday'] as $hr)
                            @php
                                $maxHr = $trends['hourlyToday']->max('requests') ?: 1;
                                $pct = ($hr->requests / $maxHr) * 100;
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-14 text-xs text-gray-500">{{ str_pad($hr->hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                                <div class="flex-1">
                                    <div class="h-4 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-4 rounded-full bg-info-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($hr->requests) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-gray-400">${{ number_format($hr->raw_cost, 4) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- Daily Summaries Table --}}
            <x-filament::section heading="Daily Summaries (Aggregated)" class="mt-4">
                @if ($trends['dailySummaries']->isEmpty())
                    <p class="text-sm text-gray-400">No daily summaries available.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Date</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Reqs</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Cached</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Failed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Input Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Output Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trends['dailySummaries'] as $ds)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ \Carbon\Carbon::parse($ds->date)->format('M d, Y') }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($ds->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->cached_requests) }}</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($ds->failed_requests > 0)
                                                <span class="text-danger-600">{{ number_format($ds->failed_requests) }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->total_input_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->total_output_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($ds->total_estimated_cost_usd, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- ══════════════ CHATS TAB ══════════════ --}}
        @if ($activeTab === 'chats' && isset($chatData))
            {{-- Chat Stats --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_chats') }}</p>
                        <p class="text-2xl font-bold text-primary-600">{{ number_format($chatData['chatStats']['totalChats']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_messages') }}</p>
                        <p class="text-2xl font-bold text-info-600">{{ number_format($chatData['chatStats']['totalMessages']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Avg Msgs/Chat</p>
                        <p class="text-2xl font-bold text-warning-600">{{ $chatData['chatStats']['avgMessagesPerChat'] }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Chat Tokens</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($chatData['chatStats']['totalTokens']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Chat Cost</p>
                        <p class="text-2xl font-bold text-success-600">${{ number_format($chatData['chatStats']['totalCost'], 4) }}</p>
                    </div>
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                {{-- Chat List --}}
                <x-filament::section heading="{{ __('ai.chats') }}">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[32rem] overflow-y-auto">
                        @forelse ($chatData['chats'] as $chat)
                            <button
                                wire:click="selectChat('{{ $chat->id }}')"
                                @class([
                                    'w-full text-start px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-white/5',
                                    'bg-primary-50 dark:bg-primary-500/10' => $selectedChatId === $chat->id,
                                ])
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-sm">{{ $chat->user?->name ?? 'Anonymous' }}</span>
                                    <span class="text-xs text-gray-400">{{ $chat->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-400">{{ $chat->messages_count }} msg{{ $chat->messages_count !== 1 ? __('ai.msgs') : __('ai.msg') }}</span>
                                    @if ($chat->total_tokens)
                                        <span class="text-xs text-gray-400">· {{ number_format($chat->total_tokens) }} tokens</span>
                                    @endif
                                    @if ($chat->total_cost_usd)
                                        <span class="text-xs text-gray-400">· ${{ number_format($chat->total_cost_usd, 4) }}</span>
                                    @endif
                                </div>
                                @if ($chat->title)
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $chat->title }}</p>
                                @endif
                            </button>
                        @empty
                            <p class="px-3 py-8 text-center text-sm text-gray-400">No chats for this store</p>
                        @endforelse
                    </div>
                </x-filament::section>

                {{-- Chat Messages --}}
                <x-filament::section heading="{{ $chatData['selectedChat'] ? ($chatData['selectedChat']->title ?? 'Chat Detail') : 'Select a chat' }}">
                    @if ($chatData['selectedChat'])
                        <div class="mb-3 flex items-center gap-3 text-xs text-gray-500">
                            <span>{{ $chatData['selectedChat']->user?->name ?? 'Anonymous' }}</span>
                            <span>·</span>
                            <span>{{ $chatData['selectedChat']->created_at->format('M d, Y H:i') }}</span>
                            <button wire:click="clearChat" class="ms-auto text-danger-500 hover:text-danger-700">✕</button>
                        </div>
                        <div class="space-y-3 max-h-[28rem] overflow-y-auto">
                            @foreach ($chatData['chatMessages'] as $msg)
                                <div @class([
                                    'rounded-lg px-3 py-2 text-sm',
                                    'bg-gray-100 dark:bg-gray-800' => $msg->role === 'user',
                                    'bg-primary-50 dark:bg-primary-500/10' => $msg->role === 'assistant',
                                    'bg-warning-50 dark:bg-warning-500/10 text-xs italic' => $msg->role === 'system',
                                ])>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-medium text-gray-500">{{ __('ai.' . $msg->role) }}</span>
                                        <span class="text-xs text-gray-400">{{ $msg->created_at->format('H:i:s') }}</span>
                                    </div>
                                    <div class="prose prose-sm dark:prose-invert max-w-none">{!! nl2br(e($msg->content)) !!}</div>
                                    @if ($msg->input_tokens || $msg->output_tokens)
                                        <p class="text-xs text-gray-400 mt-1">{{ number_format(($msg->input_tokens ?? 0) + ($msg->output_tokens ?? 0)) }} {{ __('ai.tokens') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-sm text-gray-400 py-12">{{ __('ai.select_chat_list') }}</p>
                    @endif
                </x-filament::section>
            </div>
        @endif

        {{-- ══════════════ LOGS TAB ══════════════ --}}
        @if ($activeTab === 'logs' && isset($logData))
            <x-filament::section heading="Recent Usage Logs ({{ number_format($logData['totalLogs']) }} total)">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Time</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Model</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">In Tokens</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Out Tokens</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Latency</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500">Cached</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logData['logs'] as $log)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $log->created_at->format('M d H:i:s') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                            {{ $log->feature_slug }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $log->model_used ?? '—' }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($log->input_tokens) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($log->output_tokens) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($log->estimated_cost_usd, 6) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($log->billed_cost_usd ?? $log->estimated_cost_usd, 6) }}</td>
                                    <td class="px-3 py-2 text-end text-xs {{ ($log->latency_ms ?? 0) > 5000 ? 'text-danger-600' : 'text-gray-500' }}">{{ number_format($log->latency_ms ?? 0) }}ms</td>
                                    <td class="px-3 py-2 text-center">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $log->status === 'success' || $log->status?->value === 'success',
                                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $log->status === 'error' || $log->status?->value === 'error',
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => ! in_array(is_object($log->status) ? $log->status->value : $log->status, ['success', 'error']),
                                        ])>{{ is_object($log->status) ? $log->status->value : $log->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs">
                                        @if ($log->response_cached)
                                            <span class="text-info-600">✓</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($log->error_message)
                                    <tr class="bg-danger-50 dark:bg-danger-500/5">
                                        <td colspan="10" class="px-3 py-1 text-xs text-danger-600">
                                            Error: {{ $log->error_message }}
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="10" class="px-3 py-8 text-center text-gray-400">No usage logs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if ($logData['totalPages'] > 1)
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500">Page {{ $logPage }} of {{ $logData['totalPages'] }} ({{ number_format($logData['totalLogs']) }} total)</p>
                        <div class="flex items-center gap-2">
                            @if ($logPage > 1)
                                <x-filament::button wire:click="prevLogPage" size="xs" color="gray">← Previous</x-filament::button>
                            @endif
                            @if ($logPage < $logData['totalPages'])
                                <x-filament::button wire:click="nextLogPage" size="xs" color="gray">Next →</x-filament::button>
                            @endif
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

    @endif
</x-filament-panels::page>
