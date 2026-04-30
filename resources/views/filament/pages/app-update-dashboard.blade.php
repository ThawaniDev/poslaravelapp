<x-filament-panels::page>
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    {{-- KPI Row                                                              --}}
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

        {{-- Total Releases --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-400/10">
                    <x-heroicon-o-rocket-launch class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('updates.total_releases') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalReleases }}</p>
                    <p class="text-xs text-success-600 dark:text-success-400">{{ $activeReleases }} {{ __('updates.active') }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Installation Rate --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg {{ $installationRate >= 80 ? 'bg-success-50 dark:bg-success-400/10' : ($installationRate >= 50 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-danger-50 dark:bg-danger-400/10') }}">
                    <x-heroicon-o-arrow-trending-up class="w-6 h-6 {{ $installationRate >= 80 ? 'text-success-600 dark:text-success-400' : ($installationRate >= 50 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('updates.installation_rate') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $installationRate }}%</p>
                    <p class="text-xs text-gray-400">{{ __('updates.last_30_days') }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Installed --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-success-50 dark:bg-success-400/10">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('updates.installed_count') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $funnelCounts['installed'] ?? 0 }}</p>
                    <p class="text-xs text-gray-400">{{ $funnelCounts['pending'] ?? 0 }} {{ __('updates.pending') }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Failed --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg {{ ($funnelCounts['failed'] ?? 0) > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-gray-50 dark:bg-gray-700' }}">
                    <x-heroicon-o-x-circle class="w-6 h-6 {{ ($funnelCounts['failed'] ?? 0) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('updates.failed_count') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $funnelCounts['failed'] ?? 0 }}</p>
                    <p class="text-xs text-gray-400">{{ __('updates.last_30_days') }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- ──────────────────────────────────────────────────────────────────── --}}
    {{-- Latest Active Releases per Platform                                  --}}
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    <x-filament::section :heading="__('updates.latest_by_platform')" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse ($latestByPlatform as $platformValue => $release)
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        @if ($platformValue === 'android')
                            <x-heroicon-o-device-phone-mobile class="w-5 h-5 text-green-500" />
                        @else
                            <x-heroicon-o-device-phone-mobile class="w-5 h-5 text-gray-400" />
                        @endif
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white capitalize">{{ $platformValue }}</p>
                            @if ($release)
                                <p class="text-xs text-gray-500">
                                    v{{ $release->version_number }}
                                    &bull; {{ $release->channel?->value ?? '–' }}
                                    @if ($release->rollout_percentage < 100)
                                        &bull; {{ $release->rollout_percentage }}% {{ __('updates.rollout') }}
                                    @endif
                                </p>
                            @else
                                <p class="text-xs text-gray-400">{{ __('updates.no_active_release') }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($release)
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">
                            {{ __('updates.active') }}
                        </span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 col-span-2">{{ __('updates.no_releases_yet') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    {{-- ──────────────────────────────────────────────────────────────────── --}}
    {{-- Adoption Funnel                                                       --}}
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    <x-filament::section :heading="__('updates.adoption_funnel')" class="mb-6">
        <p class="text-xs text-gray-400 mb-4">{{ __('updates.funnel_note') }}</p>
        <div class="space-y-2">
            @php
                $funnel = [
                    'pending'     => ['label' => __('updates.stat_status_pending'),     'color' => 'bg-gray-400'],
                    'downloading' => ['label' => __('updates.stat_status_downloading'), 'color' => 'bg-blue-400'],
                    'downloaded'  => ['label' => __('updates.stat_status_downloaded'),  'color' => 'bg-cyan-400'],
                    'installed'   => ['label' => __('updates.stat_status_installed'),   'color' => 'bg-success-500'],
                    'failed'      => ['label' => __('updates.stat_status_failed'),      'color' => 'bg-danger-500'],
                ];
                $maxVal = max(1, max(array_values($funnelCounts)));
            @endphp
            @foreach ($funnel as $key => $meta)
                @php $count = $funnelCounts[$key] ?? 0; $pct = round($count / $maxVal * 100); @endphp
                <div class="flex items-center gap-3">
                    <span class="w-24 text-xs text-right text-gray-500 dark:text-gray-400 shrink-0">{{ $meta['label'] }}</span>
                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div class="{{ $meta['color'] }} h-4 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-12 text-xs text-gray-700 dark:text-gray-300 shrink-0 font-mono">{{ number_format($count) }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- ──────────────────────────────────────────────────────────────────── --}}
    {{-- Auto-Rollback Candidates                                              --}}
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    @if ($rollbackCandidates->isNotEmpty())
    <x-filament::section :heading="__('updates.rollback_candidates')" class="mb-6">
        <div class="rounded-lg border border-danger-200 dark:border-danger-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-danger-50 dark:bg-danger-900/30">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-danger-700 dark:text-danger-300 uppercase">{{ __('updates.version') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-danger-700 dark:text-danger-300 uppercase">{{ __('updates.platform') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-danger-700 dark:text-danger-300 uppercase">{{ __('updates.failure_rate') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($rollbackCandidates as $release)
                        @php
                            $total = \App\Domain\AppUpdateManagement\Models\AppUpdateStat::where('app_release_id', $release->id)->count();
                            $failed = \App\Domain\AppUpdateManagement\Models\AppUpdateStat::where('app_release_id', $release->id)->where('status', 'failed')->count();
                            $rate = $total > 0 ? round($failed / $total * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-white">v{{ $release->version_number }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 capitalize">{{ $release->platform?->value }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                    {{ $rate }}%
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ \App\Filament\Resources\AppReleaseResource::getUrl('edit', ['record' => $release]) }}"
                                   class="text-xs text-primary-600 hover:underline">
                                    {{ __('updates.review') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @endif

    {{-- ──────────────────────────────────────────────────────────────────── --}}
    {{-- Recent Failures                                                       --}}
    {{-- ──────────────────────────────────────────────────────────────────── --}}
    @if ($recentFailures->isNotEmpty())
    <x-filament::section :heading="__('updates.recent_failures')">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('updates.store') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('updates.version') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('updates.error') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.time') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($recentFailures as $stat)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">{{ Str::limit($stat->store_id, 8) }}</td>
                            <td class="px-4 py-2 text-xs font-mono text-gray-900 dark:text-white">
                                v{{ $stat->appRelease?->version_number ?? '–' }}
                            </td>
                            <td class="px-4 py-2 text-xs text-danger-600 dark:text-danger-400 max-w-xs truncate">
                                {{ $stat->error_message ?? __('updates.unknown_error') }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-400">
                                {{ $stat->updated_at?->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @endif
</x-filament-panels::page>
