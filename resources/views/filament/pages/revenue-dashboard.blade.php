@php
    /** @var float $mrr */
    /** @var float $arr */
    /** @var \Illuminate\Support\Collection $revenueTrend */
    /** @var \Illuminate\Support\Collection $revenueByPlan */
    /** @var int $failedPayments */
    /** @var int $upcomingRenewals */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.mrr') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($mrr, 2) }} SAR</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.arr') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($arr, 2) }} SAR</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.failed_payments') }}</p>
                <p class="text-3xl font-bold {{ $failedPayments > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $failedPayments }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.upcoming_renewals') }}</p>
                <p class="text-3xl font-bold text-warning-600">{{ $upcomingRenewals }}</p>
                <p class="text-xs text-gray-400">{{ __('analytics.next_7_days') }}</p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Revenue Trend Chart --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.revenue_trend') }}</x-slot>
            @if($revenueTrend->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Revenue by Plan Pie --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.revenue_by_plan') }}</x-slot>
            @if($revenueByPlan->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_plan_data') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="revenueByPlanChart"></canvas>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Revenue by Plan Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.revenue_by_plan') }} — {{ __('analytics.details') }}</x-slot>
        @if($revenueByPlan->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_plan_data') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-left">{{ __('analytics.plan') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.active_stores') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.mrr') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($revenueByPlan as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 font-medium">{{ $row['plan'] }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($row['active']) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($row['mrr'], 2) }} SAR</td>
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

    @if($revenueTrend->isNotEmpty())
    new Chart(document.getElementById('revenueTrendChart'), {
        type: 'line',
        data: {
            labels: @json($revenueTrend->pluck('date')),
            datasets: [
                {
                    label: '{{ __('analytics.mrr') }}',
                    data: @json($revenueTrend->pluck('mrr')),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y',
                },
                {
                    label: '{{ __('analytics.gmv') }}',
                    data: @json($revenueTrend->pluck('gmv')),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } },
                y1: { position: 'right', beginAtZero: true, ticks: { color: textColor }, grid: { drawOnChartArea: false } },
                x: { ticks: { color: textColor, maxRotation: 45 }, grid: { display: false } }
            },
            plugins: { legend: { labels: { color: textColor } } }
        }
    });
    @endif

    @if($revenueByPlan->isNotEmpty())
    new Chart(document.getElementById('revenueByPlanChart'), {
        type: 'doughnut',
        data: {
            labels: @json($revenueByPlan->pluck('plan')),
            datasets: [{
                data: @json($revenueByPlan->pluck('mrr')),
                backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
        }
    });
    @endif
});
</script>
@endpush
