@php
    /** @var int $totalStores */
    /** @var int $activeStores */
    /** @var int $inactiveStores */
    /** @var \Illuminate\Support\Collection $healthSummary */
    /** @var float $zatcaRate */
    /** @var int $storesWithErrors */
    /** @var int $totalMonitored */
    /** @var int $newThisMonth */
    /** @var \Illuminate\Support\Collection $topStores */
    /** @var \Illuminate\Support\Collection $storesByCity */
    /** @var \Illuminate\Support\Collection $businessTypes */
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
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.active_stores') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($activeStores) }}</p>
                <p class="text-xs text-gray-400">{{ $totalStores > 0 ? round(($activeStores / $totalStores) * 100, 1) : 0 }}%</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.inactive_stores') }}</p>
                <p class="text-3xl font-bold text-gray-600">{{ number_format($inactiveStores) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.new_this_month') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ number_format($newThisMonth) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.zatca_compliance') }}</p>
                <p class="text-3xl font-bold {{ $zatcaRate >= 90 ? 'text-success-600' : ($zatcaRate >= 70 ? 'text-warning-600' : 'text-danger-600') }}">{{ $zatcaRate }}%</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Health Summary --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.store_health_summary') }}</x-slot>
        <div class="grid auto-cols-fr grid-flow-col gap-4">
            <div class="rounded-lg border p-4 text-center dark:border-gray-700">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('analytics.monitored') }}</p>
                <p class="text-2xl font-bold mt-1">{{ number_format($totalMonitored) }}</p>
            </div>
            <div class="rounded-lg border p-4 text-center dark:border-gray-700">
                <p class="text-xs uppercase tracking-wide text-danger-500">{{ __('analytics.with_errors') }}</p>
                <p class="text-2xl font-bold mt-1 {{ $storesWithErrors > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($storesWithErrors) }}</p>
            </div>
            @foreach($healthSummary as $status => $count)
                <div class="rounded-lg border p-4 text-center dark:border-gray-700">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst($status) }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($count) }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Stores by City Chart --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.stores_by_city') }}</x-slot>
            @if($storesByCity->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="cityChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Business Type Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.business_type_breakdown') }}</x-slot>
            @if($businessTypes->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div style="height: 300px;">
                    <canvas id="businessTypeChart"></canvas>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Top 20 Stores by GMV --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('analytics.top_stores_by_gmv') }}</x-slot>
        @if($topStores->isEmpty())
            <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-left">#</th>
                            <th class="py-2 px-3 text-left">{{ __('analytics.store_name') }}</th>
                            <th class="py-2 px-3 text-left">{{ __('analytics.city') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.orders') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('analytics.gmv') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topStores as $i => $store)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 text-gray-400">{{ $i + 1 }}</td>
                                <td class="py-2 px-3 font-medium">{{ $store->name }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $store->city ?? '—' }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($store->order_count) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($store->total_gmv, 2) }} SAR</td>
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

    @if($storesByCity->isNotEmpty())
    new Chart(document.getElementById('cityChart'), {
        type: 'bar',
        data: {
            labels: @json($storesByCity->pluck('city')),
            datasets: [{
                label: '{{ __('analytics.stores') }}',
                data: @json($storesByCity->pluck('count')),
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
    @endif

    @if($businessTypes->isNotEmpty())
    new Chart(document.getElementById('businessTypeChart'), {
        type: 'doughnut',
        data: {
            labels: @json($businessTypes->pluck('type')->map(fn($t) => ucfirst(str_replace('_', ' ', $t)))),
            datasets: [{
                data: @json($businessTypes->pluck('count')),
                backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'],
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
