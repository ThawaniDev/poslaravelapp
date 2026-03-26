<x-filament-panels::page>
    {{-- Stats Overview --}}
    <div class="grid auto-cols-fr grid-flow-col gap-4 mb-6">
        {{-- Failed Jobs --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-danger-50 dark:bg-danger-400/10">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('infrastructure.failed_jobs') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $failedJobsCount }}</p>
                    <p class="text-xs text-gray-400">{{ __('infrastructure.last_24h') }}: {{ $failedJobsLast24h }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Backups --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-success-50 dark:bg-success-400/10">
                    <x-heroicon-o-circle-stack class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('infrastructure.database_backups') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $backupsCompleted }}</p>
                    @if ($backupsFailed > 0)
                        <p class="text-xs text-danger-500">{{ $backupsFailed }} {{ __('infrastructure.failed') }}</p>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Health Checks --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-400/10">
                    <x-heroicon-o-heart class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('infrastructure.services') }}</p>
                    <div class="flex items-center gap-2">
                        <span class="text-success-600 font-bold">{{ $healthyServices }}</span>
                        @if ($warningServices > 0)
                            <span class="text-warning-600 font-bold">{{ $warningServices }}</span>
                        @endif
                        @if ($criticalServices > 0)
                            <span class="text-danger-600 font-bold">{{ $criticalServices }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Last Backup --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-warning-50 dark:bg-warning-400/10">
                    <x-heroicon-o-clock class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('infrastructure.last_backup') }}</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                        {{ $lastBackup?->completed_at?->diffForHumans() ?? __('infrastructure.never') }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Server Info --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('infrastructure.server_info') }}</x-slot>
            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($serverInfo as $key => $value)
                    <div class="flex justify-between py-2">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        {{-- Latest Health Checks --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('infrastructure.latest_health_checks') }}</x-slot>
            @if ($latestHealthChecks->isEmpty())
                <p class="text-sm text-gray-500">{{ __('infrastructure.no_health_checks') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($latestHealthChecks as $check)
                        <div class="flex items-center justify-between py-1">
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'inline-block w-2 h-2 rounded-full',
                                    'bg-success-500' => $check->status === 'healthy',
                                    'bg-warning-500' => $check->status === 'warning',
                                    'bg-danger-500' => $check->status === 'critical',
                                ])></span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $check->service }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-500">{{ $check->response_time_ms }}ms</span>
                                <span class="text-xs text-gray-400 ml-2">{{ $check->checked_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Recent Failed Jobs --}}
    @if ($recentFailedJobs->isNotEmpty())
        <x-filament::section class="mt-6">
            <x-slot name="heading">{{ __('infrastructure.recent_failed_jobs') }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="pb-2">{{ __('infrastructure.queue') }}</th>
                            <th class="pb-2">{{ __('infrastructure.job_name') }}</th>
                            <th class="pb-2">{{ __('infrastructure.failed_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($recentFailedJobs as $job)
                            <tr>
                                <td class="py-2">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs font-medium">
                                        {{ $job->queue }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-900 dark:text-white">
                                    {{ class_basename(json_decode($job->payload, true)['displayName'] ?? 'Unknown') }}
                                </td>
                                <td class="py-2 text-gray-500">{{ $job->failed_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
