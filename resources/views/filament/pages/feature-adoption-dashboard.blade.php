@php
    /** @var int $totalStores */
    /** @var \Illuminate\Support\Collection $features */
    /** @var \Illuminate\Support\Collection $trend */
    /** @var ?string $latestDate */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.total_stores') }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($totalStores) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.features_tracked') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ $features->count() }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.snapshot_date') }}</p>
                <p class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ $latestDate ?? '—' }}</p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Feature Adoption Rates — Horizontal Bar Chart --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.feature_adoption_rates') }}</x-slot>
            @if($features->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: {{ max(200, $features->count() * 32) }}px;">
                    <canvas id="adoptionBarChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Adoption Trend — Line Chart --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.adoption_trend_30d') }}</x-slot>
            @if($trend->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="adoptionTrendChart"></canvas>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Feature Detail Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.feature_details') }}</x-slot>
        @if($features->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-left">{{ __('analytics.feature') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.stores_using') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.total_events') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.adoption_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($features as $feature)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 font-mono text-xs">{{ $feature['feature_key'] }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($feature['stores_using']) }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($feature['total_events']) }}</td>
                                <td class="py-2 px-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="h-2 w-16 rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-2 rounded-full {{ $feature['adoption_rate'] >= 50 ? 'bg-success-500' : ($feature['adoption_rate'] >= 20 ? 'bg-warning-500' : 'bg-danger-500') }}" style="width: {{ min($feature['adoption_rate'], 100) }}%"></div>
                                        </div>
                                        <span>{{ $feature['adoption_rate'] }}%</span>
                                    </div>
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

    @if($features->isNotEmpty())
    new Chart(document.getElementById('adoptionBarChart'), {
        type: 'bar',
        data: {
            labels: @json($features->pluck('feature_key')->map(fn($k) => str_replace('_', ' ', ucfirst($k)))),
            datasets: [{
                label: '{{ __('analytics.adoption_rate') }} %',
                data: @json($features->pluck('adoption_rate')),
                backgroundColor: {!! json_encode($features->map(fn($f) => $f['adoption_rate'] >= 50 ? 'rgba(16,185,129,0.7)' : ($f['adoption_rate'] >= 20 ? 'rgba(245,158,11,0.7)' : 'rgba(239,68,68,0.7)'))->values()) !!},
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, max: 100, ticks: { color: textColor, callback: v => v + '%' }, grid: { color: gridColor } },
                y: { ticks: { color: textColor, font: { size: 11 } }, grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
    @endif

    @if($trend->isNotEmpty())
    new Chart(document.getElementById('adoptionTrendChart'), {
        type: 'line',
        data: {
            labels: @json($trend->pluck('date')),
            datasets: [
                {
                    label: '{{ __('analytics.stores_using') }}',
                    data: @json($trend->pluck('total_using')),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y',
                },
                {
                    label: '{{ __('analytics.total_events') }}',
                    data: @json($trend->pluck('total_events')),
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
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
                x: { ticks: { color: textColor, maxTicksLimit: 10 }, grid: { color: gridColor } },
                y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: '{{ __('analytics.stores_using') }}', color: textColor }, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, title: { display: true, text: '{{ __('analytics.total_events') }}', color: textColor }, ticks: { color: textColor, precision: 0 }, grid: { drawOnChartArea: false } }
            },
            plugins: { legend: { labels: { color: textColor } } }
        }
    });
    @endif
});
</script>
@endpush
