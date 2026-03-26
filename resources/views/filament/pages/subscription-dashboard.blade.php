@php
    /** @var \Illuminate\Support\Collection $statusCounts */
    /** @var \Illuminate\Support\Collection $lifecycleTrend */
    /** @var int $totalChurn */
    /** @var float $conversionRate */
    /** @var int $totalActive */
    /** @var int $totalTrials */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.active_subscriptions') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($totalActive) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.trial_subscriptions') }}</p>
                <p class="text-3xl font-bold text-warning-600">{{ number_format($totalTrials) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.churn_30d') }}</p>
                <p class="text-3xl font-bold {{ $totalChurn > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $totalChurn }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.trial_conversion') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ $conversionRate }}%</p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Status Breakdown Pie --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.status_breakdown') }}</x-slot>
            @if($statusCounts->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="statusPieChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Status Breakdown Cards --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.status_breakdown') }} — {{ __('analytics.details') }}</x-slot>
            <div class="grid auto-cols-fr grid-flow-col gap-4">
                @foreach($statusCounts as $status => $count)
                    <div class="rounded-lg border p-4 text-center dark:border-gray-700">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ ucfirst($status) }}</p>
                        <p class="text-2xl font-bold mt-1">{{ number_format($count) }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    {{-- Lifecycle Trend Chart --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.lifecycle_trend') }}</x-slot>
        @if($lifecycleTrend->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div style="height: 350px;">
                <canvas id="lifecycleTrendChart"></canvas>
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

    @if($statusCounts->isNotEmpty())
    new Chart(document.getElementById('statusPieChart'), {
        type: 'doughnut',
        data: {
            labels: @json($statusCounts->keys()->map(fn($s) => ucfirst($s))),
            datasets: [{
                data: @json($statusCounts->values()),
                backgroundColor: ['#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6','#ec4899'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
        }
    });
    @endif

    @if($lifecycleTrend->isNotEmpty())
    new Chart(document.getElementById('lifecycleTrendChart'), {
        type: 'line',
        data: {
            labels: @json($lifecycleTrend->pluck('date')),
            datasets: [
                {
                    label: '{{ __('analytics.active') }}',
                    data: @json($lifecycleTrend->pluck('active')),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: '{{ __('analytics.trial') }}',
                    data: @json($lifecycleTrend->pluck('trial')),
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: '{{ __('analytics.churned') }}',
                    data: @json($lifecycleTrend->pluck('churned')),
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
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, stacked: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                x: { ticks: { color: textColor, maxRotation: 45 }, grid: { display: false } }
            },
            plugins: { legend: { labels: { color: textColor } } }
        }
    });
    @endif
});
</script>
@endpush
