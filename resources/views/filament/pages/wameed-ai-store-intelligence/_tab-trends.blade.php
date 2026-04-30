            {{-- Daily Usage Chart (Bar viz) --}}
            <x-filament::section :heading="__('ai.section_daily_usage_last_days', ['days' => $dateRange])">
                @if ($trends['dailyUsage']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_daily_usage') }}</p>
                @else
                    <div class="space-y-2">
                        @foreach ($trends['dailyUsage'] as $day)
                            @php
                                $maxReqs = $trends['dailyUsage']->max('requests') ?: 1;
                                $pct = ($day->requests / $maxReqs) * 100;
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</span>
                                <div class="flex-1">
                                    <div class="h-5 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-5 rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($day->requests) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-gray-400">${{ number_format($day->raw_cost, 4) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-success-600">${{ number_format($day->billed_cost, 4) }}</span>
                                <span class="w-14 text-right text-xs font-mono {{ round($day->avg_latency) > 5000 ? 'text-danger-500' : 'text-gray-400' }}">{{ number_format(round($day->avg_latency)) }}ms</span>
                                <span class="w-8 text-right text-xs font-mono {{ $day->errors > 0 ? 'text-danger-500' : 'text-gray-400' }}">{{ $day->errors }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400">
                        <span class="w-20">{{ __('ai.col_date') }}</span>
                        <span class="flex-1">{{ __('ai.col_volume') }}</span>
                        <span class="w-14 text-right">{{ __('ai.col_reqs') }}</span>
                        <span class="w-20 text-right">{{ __('ai.col_raw_cost_abbr') }}</span>
                        <span class="w-20 text-right">{{ __('ai.col_billed_abbr') }}</span>
                        <span class="w-14 text-right">{{ __('ai.col_latency') }}</span>
                        <span class="w-8 text-right">{{ __('ai.col_err') }}</span>
                    </div>
                @endif
            </x-filament::section>

            {{-- Hourly Breakdown Today --}}
            <x-filament::section :heading="__('ai.section_hourly_breakdown')" class="mt-4">
                @if ($trends['hourlyToday']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_requests_today') }}</p>
                @else
                    <div class="space-y-1">
                        @foreach ($trends['hourlyToday'] as $hr)
                            @php
                                $maxHr = $trends['hourlyToday']->max('requests') ?: 1;
                                $pct = ($hr->requests / $maxHr) * 100;
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-14 text-xs text-gray-500">{{ str_pad($hr->hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                                <div class="flex-1">
                                    <div class="h-4 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-4 rounded-full bg-info-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($hr->requests) }}</span>
                                <span class="w-20 text-right text-xs font-mono text-gray-400">${{ number_format($hr->raw_cost, 4) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- Daily Summaries Table --}}
            <x-filament::section :heading="__('ai.section_daily_summaries')" class="mt-4">
                @if ($trends['dailySummaries']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_daily_summaries') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_date') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_total_reqs') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_cached') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_failed') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_input_tokens') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_output_tokens') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_est_cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trends['dailySummaries'] as $ds)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ \Carbon\Carbon::parse($ds->date)->format('M d, Y') }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($ds->total_requests) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->cached_requests) }}</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($ds->failed_requests > 0)
                                                <span class="text-danger-600">{{ number_format($ds->failed_requests) }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->total_input_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($ds->total_output_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($ds->total_estimated_cost_usd, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
