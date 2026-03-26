@php
    /** @var int $totalSent */
    /** @var int $totalDelivered */
    /** @var int $totalOpened */
    /** @var int $totalFailed */
    /** @var float $deliveryRate */
    /** @var float $openRate */
    /** @var float $failureRate */
    /** @var \Illuminate\Support\Collection $channelBreakdown */
    /** @var \Illuminate\Support\Collection $templateStats */
    /** @var \Illuminate\Support\Collection $dailyTrend */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.total_sent') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($totalSent) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.total_delivered') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($totalDelivered) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.total_opened') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ number_format($totalOpened) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.total_failed') }}</p>
                <p class="text-3xl font-bold {{ $totalFailed > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($totalFailed) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.delivery_rate') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ $deliveryRate }}%</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.open_rate') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ $openRate }}%</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.failure_rate') }}</p>
                <p class="text-3xl font-bold {{ $failureRate > 5 ? 'text-danger-600' : 'text-success-600' }}">{{ $failureRate }}%</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Daily Volume Trend --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.notification_volume_trend') }}</x-slot>
        @if($dailyTrend->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div style="height: 300px;">
                <canvas id="dailyTrendChart"></canvas>
            </div>
        @endif
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Channel Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.channel_breakdown') }}</x-slot>
            @if($channelBreakdown->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 280px;">
                    <canvas id="channelChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Channel Stats Table --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.channel_stats') }}</x-slot>
            @if($channelBreakdown->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="py-2 px-3 text-left">{{ __('analytics.channel') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('analytics.sent') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('analytics.delivered') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('analytics.failed') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($channelBreakdown as $ch)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-3 font-medium capitalize">{{ $ch['channel'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($ch['sent']) }}</td>
                                    <td class="py-2 px-3 text-right text-success-600">{{ number_format($ch['delivered']) }}</td>
                                    <td class="py-2 px-3 text-right {{ $ch['failed'] > 0 ? 'text-danger-600' : '' }}">{{ number_format($ch['failed']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Per-Template Stats --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.per_template_stats') }}</x-slot>
        @if($templateStats->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-left">{{ __('analytics.event_key') }}</th>
                            <th class="py-2 px-3 text-left">{{ __('analytics.channel') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.total_sent') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.delivered') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.opened') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.failed') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.delivery_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templateStats as $tpl)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 font-mono text-xs">{{ $tpl['event_key'] }}</td>
                                <td class="py-2 px-3 capitalize">{{ $tpl['channel'] }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($tpl['total']) }}</td>
                                <td class="py-2 px-3 text-right text-success-600">{{ number_format($tpl['delivered']) }}</td>
                                <td class="py-2 px-3 text-right text-info-600">{{ number_format($tpl['opened']) }}</td>
                                <td class="py-2 px-3 text-right {{ $tpl['failed'] > 0 ? 'text-danger-600' : '' }}">{{ number_format($tpl['failed']) }}</td>
                                <td class="py-2 px-3 text-right">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block h-2 w-2 rounded-full {{ $tpl['delivery_rate'] >= 90 ? 'bg-success-500' : ($tpl['delivery_rate'] >= 70 ? 'bg-warning-500' : 'bg-danger-500') }}"></span>
                                        {{ $tpl['delivery_rate'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9ca3af' : '#6b7280';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    @if($dailyTrend->isNotEmpty())
    new Chart(document.getElementById('dailyTrendChart'), {
        type: 'line',
        data: {
            labels: @json($dailyTrend->pluck('date')),
            datasets: [
                {
                    label: '{{ __('analytics.total_sent') }}',
                    data: @json($dailyTrend->pluck('total')),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: '{{ __('analytics.failed') }}',
                    data: @json($dailyTrend->pluck('failed')),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                x: { ticks: { color: textColor, maxRotation: 45 }, grid: { display: false } }
            },
            plugins: { legend: { labels: { color: textColor } } }
        }
    });
    @endif

    @if($channelBreakdown->isNotEmpty())
    new Chart(document.getElementById('channelChart'), {
        type: 'bar',
        data: {
            labels: @json($channelBreakdown->pluck('channel')),
            datasets: [
                {
                    label: '{{ __('analytics.sent') }}',
                    data: @json($channelBreakdown->pluck('sent')),
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderRadius: 4,
                },
                {
                    label: '{{ __('analytics.delivered') }}',
                    data: @json($channelBreakdown->pluck('delivered')),
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 4,
                },
                {
                    label: '{{ __('analytics.failed') }}',
                    data: @json($channelBreakdown->pluck('failed')),
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                x: { ticks: { color: textColor }, grid: { display: false } }
            },
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
        }
    });
    @endif
});
</script>
@endpush
