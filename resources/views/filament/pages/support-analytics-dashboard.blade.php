@php
    /** @var int $openCount */
    /** @var int $totalThisMonth */
    /** @var float $avgFirstResponse */
    /** @var float $avgResolution */
    /** @var float $slaRate */
    /** @var \Illuminate\Support\Collection $volumeTrend */
    /** @var \Illuminate\Support\Collection $categoryBreakdown */
    /** @var \Illuminate\Support\Collection $priorityBreakdown */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.open_tickets') }}</p>
                <p class="text-3xl font-bold {{ $openCount > 10 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($openCount) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.tickets_this_month') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($totalThisMonth) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.avg_first_response') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ $avgFirstResponse }}h</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.avg_resolution_time') }}</p>
                <p class="text-3xl font-bold text-warning-600">{{ $avgResolution }}h</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.sla_compliance') }}</p>
                <p class="text-3xl font-bold {{ $slaRate >= 90 ? 'text-success-600' : ($slaRate >= 70 ? 'text-warning-600' : 'text-danger-600') }}">{{ $slaRate }}%</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Ticket Volume Trend Chart --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.ticket_volume_trend') }}</x-slot>
        @if($volumeTrend->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div style="height: 300px;">
                <canvas id="volumeTrendChart"></canvas>
            </div>
        @endif
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Category Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.category_breakdown') }}</x-slot>
            @if($categoryBreakdown->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Priority Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.priority_breakdown') }}</x-slot>
            @if($priorityBreakdown->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="priorityChart"></canvas>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9ca3af' : '#6b7280';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    @if($volumeTrend->isNotEmpty())
    new Chart(document.getElementById('volumeTrendChart'), {
        type: 'bar',
        data: {
            labels: @json($volumeTrend->pluck('date')),
            datasets: [{
                label: '{{ __('analytics.tickets') }}',
                data: @json($volumeTrend->pluck('count')),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
                borderRadius: 4,
            }]
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

    @if($categoryBreakdown->isNotEmpty())
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: @json($categoryBreakdown->pluck('category')),
            datasets: [{
                data: @json($categoryBreakdown->pluck('count')),
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

    @if($priorityBreakdown->isNotEmpty())
    new Chart(document.getElementById('priorityChart'), {
        type: 'doughnut',
        data: {
            labels: @json($priorityBreakdown->pluck('priority')),
            datasets: [{
                data: @json($priorityBreakdown->pluck('count')),
                backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ef4444'],
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
