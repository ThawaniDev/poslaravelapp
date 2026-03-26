@php
    /** @var int $queueDepth */
    /** @var int $failedJobs */
    /** @var int $failedLast24h */
    /** @var int $healthyCount */
    /** @var int $warningCount */
    /** @var int $criticalCount */
    /** @var int $avgLatency */
    /** @var \Illuminate\Support\Collection $topErrors */
    /** @var \Illuminate\Support\Collection $updateStats */
    /** @var ?\App\Domain\AppUpdateManagement\Models\AppRelease $latestRelease */
    /** @var \Illuminate\Support\Collection $failedTrend */
    /** @var \Illuminate\Support\Collection $healthChecks */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards — horizontal row --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.queue_depth') }}</p>
                <p class="text-3xl font-bold {{ $queueDepth > 100 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($queueDepth) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.failed_jobs') }}</p>
                <p class="text-3xl font-bold {{ $failedJobs > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($failedJobs) }}</p>
                <p class="text-xs text-gray-400">{{ __('analytics.last_24h') }}: {{ $failedLast24h }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.healthy_services') }}</p>
                <p class="text-3xl font-bold text-success-600">{{ $healthyCount }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.warning_services') }}</p>
                <p class="text-3xl font-bold {{ $warningCount > 0 ? 'text-warning-600' : 'text-success-600' }}">{{ $warningCount }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.critical_services') }}</p>
                <p class="text-3xl font-bold {{ $criticalCount > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $criticalCount }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('analytics.avg_latency') }}</p>
                <p class="text-3xl font-bold text-info-600">{{ $avgLatency }}ms</p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Failed Jobs Trend --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.failed_jobs_trend') }}</x-slot>
            @if($failedTrend->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_failed_jobs') }}</p>
            @else
                <div style="height: 280px;">
                    <canvas id="failedTrendChart"></canvas>
                </div>
            @endif
        </x-filament::section>

        {{-- Service Health --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.service_health') }}</x-slot>
            @if($healthChecks->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_data_yet') }}</p>
            @else
                <div class="space-y-2">
                    @foreach($healthChecks as $check)
                        <div class="flex items-center justify-between py-1.5 border-b dark:border-gray-700 last:border-0">
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'inline-block w-2.5 h-2.5 rounded-full',
                                    'bg-success-500' => $check->status === 'healthy',
                                    'bg-warning-500' => $check->status === 'warning',
                                    'bg-danger-500' => $check->status === 'critical',
                                ])></span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $check->service }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono text-gray-500">{{ $check->response_time_ms }}ms</span>
                                <span class="text-xs text-gray-400">{{ $check->checked_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Top Errors --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.top_errors_7d') }}</x-slot>
            @if($topErrors->isEmpty())
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_errors') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="py-2 px-3 text-left">{{ __('analytics.job_class') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('analytics.failures') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topErrors as $error)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-3 font-mono text-xs">{{ $error['job'] }}</td>
                                    <td class="py-2 px-3 text-right">
                                        <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                                            {{ $error['count'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- App Update Adoption --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('analytics.update_adoption') }}</x-slot>
            @if($latestRelease)
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('analytics.latest_release') }}: <span class="font-medium text-gray-900 dark:text-white">{{ $latestRelease->version }}</span>
                    <span class="text-xs text-gray-400 ml-2">{{ $latestRelease->released_at?->diffForHumans() }}</span>
                </p>
                @if($updateStats->isEmpty())
                    <p class="text-center text-gray-500 py-4">{{ __('analytics.no_data_yet') }}</p>
                @else
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        @foreach($updateStats as $status => $count)
                            <div class="rounded-lg border p-3 text-center dark:border-gray-700">
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $status)) }}</p>
                                <p class="text-xl font-bold mt-1">{{ number_format($count) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-center text-gray-500 py-8">{{ __('analytics.no_releases') }}</p>
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

    @if($failedTrend->isNotEmpty())
    new Chart(document.getElementById('failedTrendChart'), {
        type: 'bar',
        data: {
            labels: @json($failedTrend->pluck('date')),
            datasets: [{
                label: '{{ __('analytics.failed_jobs') }}',
                data: @json($failedTrend->pluck('count')),
                backgroundColor: 'rgba(239, 68, 68, 0.5)',
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                x: { ticks: { color: textColor }, grid: { display: false } }
            },
            plugins: { legend: { labels: { color: textColor } } }
        }
    });
    @endif
});
</script>
@endpush
