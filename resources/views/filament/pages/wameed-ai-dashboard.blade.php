<x-filament-panels::page>
    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Today's Requests</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($todayRequests) }}</p>
                <p class="text-xs text-gray-400">Total: {{ number_format($totalRequests) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Today's Cost</p>
                <p class="text-3xl font-bold text-warning-600">${{ $todayCost }}</p>
                <p class="text-xs text-gray-400">Total: ${{ $totalCost }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Avg Latency</p>
                <p class="text-3xl font-bold {{ $avgLatency > 5000 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($avgLatency) }}ms</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Cache Hit Rate</p>
                <p class="text-3xl font-bold text-info-600">{{ $cacheHitRate }}%</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Secondary Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Features</p>
                <p class="text-2xl font-bold text-primary-600">{{ $enabledFeatures }}/{{ $totalFeatures }}</p>
                <p class="text-xs text-gray-400">Enabled</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Active Stores (30d)</p>
                <p class="text-2xl font-bold text-success-600">{{ number_format($activeStores) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Error Rate (7d)</p>
                <p class="text-2xl font-bold {{ $errorRate > 5 ? 'text-danger-600' : 'text-success-600' }}">{{ $errorRate }}%</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Avg Cost/Request</p>
                <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">
                    ${{ $totalRequests > 0 ? number_format((float) str_replace(',', '', $totalCost) / $totalRequests, 4) : '0.0000' }}
                </p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Top Features --}}
        <x-filament::section heading="Top Features (Last 30 Days)">
            @if($topFeatures->isEmpty())
                <p class="text-sm text-gray-400">No usage data yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="pb-2 text-left font-medium text-gray-500 dark:text-gray-400">Feature</th>
                                <th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">Requests</th>
                                <th class="pb-2 text-right font-medium text-gray-500 dark:text-gray-400">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topFeatures as $feature)
                                <tr class="border-b dark:border-gray-700/50">
                                    <td class="py-2">
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                            {{ $feature->feature_slug }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-right font-mono">{{ number_format($feature->total_requests) }}</td>
                                    <td class="py-2 text-right font-mono">${{ number_format($feature->total_cost, 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Daily Trend --}}
        <x-filament::section heading="Daily Trend (Last 14 Days)">
            @if($dailyTrend->isEmpty())
                <p class="text-sm text-gray-400">No usage data yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($dailyTrend as $day)
                        @php
                            $maxRequests = $dailyTrend->max('requests') ?: 1;
                            $pct = ($day->requests / $maxRequests) * 100;
                        @endphp
                        <div class="flex items-center gap-3">
                            <span class="w-20 text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</span>
                            <div class="flex-1">
                                <div class="h-5 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-5 rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                            <span class="w-16 text-right text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($day->requests) }}</span>
                            <span class="w-20 text-right text-xs font-mono text-gray-400">${{ number_format($day->cost, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
