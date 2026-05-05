<x-filament-panels::page>
    <div class="space-y-6">
        {{-- ── Stats Row ─────────────────────────────────────────── --}}
        @php $stats = $this->getStats(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            {{-- Active Integrations --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('delivery.active_integrations') }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                    {{ $stats['total_integrations'] }}
                    @if($stats['error_integrations'] > 0)
                        <span class="text-sm font-normal text-danger-600">({{ $stats['error_integrations'] }} error)</span>
                    @endif
                </p>
            </div>

            {{-- 24h Sync Error Rate --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $stats['error_rate'] > 10 ? 'border-danger-300' : ($stats['error_rate'] > 3 ? 'border-warning-300' : 'border-gray-200 dark:border-gray-700') }} p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('delivery.sync_error_rate_24h') }}</p>
                <p class="text-2xl font-bold {{ $stats['error_rate'] > 10 ? 'text-danger-600' : ($stats['error_rate'] > 3 ? 'text-warning-600' : 'text-success-600') }} mt-1">
                    {{ $stats['error_rate'] }}%
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $stats['recent_sync_failed'] }}/{{ $stats['recent_sync_total'] }} failed
                </p>
            </div>

            {{-- Pending Orders --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $stats['pending_orders'] > 10 ? 'border-danger-300' : 'border-gray-200 dark:border-gray-700' }} p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('delivery.pending_orders') }}</p>
                <p class="text-2xl font-bold {{ $stats['pending_orders'] > 0 ? 'text-warning-600' : 'text-success-600' }} mt-1">
                    {{ $stats['pending_orders'] }}
                </p>
            </div>

            {{-- Push Failures --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $stats['push_failures_24h'] > 0 ? 'border-warning-300' : 'border-gray-200 dark:border-gray-700' }} p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('delivery.push_failures_24h') }}</p>
                <p class="text-2xl font-bold {{ $stats['push_failures_24h'] > 0 ? 'text-warning-600' : 'text-success-600' }} mt-1">
                    {{ $stats['push_failures_24h'] }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $stats['webhook_failures_24h'] }} {{ __('delivery.webhook_invalid_sig') }}
                </p>
            </div>
        </div>

        {{-- ── Per-Platform Breakdown ────────────────────────────── --}}
        @php $breakdown = $this->getPlatformBreakdown(); @endphp
        @if(count($breakdown) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">
                {{ __('delivery.platform_breakdown_24h') }}
            </h3>
            <div class="space-y-2">
                @foreach($breakdown as $row)
                <div class="flex items-center gap-3">
                    <div class="w-28 text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                        {{ $row['name'] }}
                    </div>
                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $row['error_rate'] > 10 ? 'bg-danger-500' : ($row['error_rate'] > 3 ? 'bg-warning-500' : 'bg-success-500') }}"
                             style="width: {{ min($row['error_rate'], 100) }}%"></div>
                    </div>
                    <div class="w-20 text-xs text-right {{ $row['error_rate'] > 10 ? 'text-danger-600' : 'text-gray-500' }}">
                        {{ $row['error_rate'] }}% ({{ $row['failed_syncs'] }}/{{ $row['total_syncs'] }})
                    </div>
                    <div class="w-20 text-xs text-right text-gray-400">
                        {{ $row['configs'] }} {{ __('delivery.stores') }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Top Error Messages ────────────────────────────────── --}}
        @php $topErrors = $this->getTopErrors(); @endphp
        @if(count($topErrors) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-danger-200 dark:border-danger-700 p-5">
            <h3 class="text-sm font-semibold text-danger-700 dark:text-danger-400 mb-3">
                {{ __('delivery.top_error_messages') }}
            </h3>
            <ul class="space-y-2">
                @foreach($topErrors as $err)
                <li class="flex items-start gap-2 text-xs">
                    <span class="inline-flex items-center justify-center min-w-[1.5rem] h-5 rounded-full bg-danger-100 text-danger-700 font-bold">
                        {{ $err['count'] }}
                    </span>
                    <span class="text-gray-600 dark:text-gray-300">
                        <span class="font-medium text-gray-800 dark:text-gray-200 mr-1">[{{ $err['platform'] }}]</span>
                        {{ $err['message'] }}
                    </span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- ── Failed Syncs Table ────────────────────────────────── --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                {{ __('delivery.recent_failed_syncs') }} ({{ __('delivery.last_50') }})
            </h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
