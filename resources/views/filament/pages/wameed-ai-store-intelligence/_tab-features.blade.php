            {{-- Models Used --}}
            <x-filament::section heading="Models Used (Last {{ $dateRange }} Days)">
                @if ($features['modelsUsed']->isEmpty())
                    <p class="text-sm text-gray-400">No model usage data.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Model</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['modelsUsed'] as $model)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $model->model_used ?? 'Unknown' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($model->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($model->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($model->raw_cost, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Feature Usage (Recent) --}}
            <x-filament::section heading="Feature Usage (Last {{ $dateRange }} Days)" class="mt-4">
                @if ($features['featureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">No feature usage data for this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Avg Latency</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Errors</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Cached</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Last Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['featureUsage'] as $feature)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                {{ $feature->feature_slug }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono font-bold">{{ number_format($feature->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($feature->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($feature->raw_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($feature->billed_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end text-xs {{ round($feature->avg_latency) > 5000 ? 'text-danger-600' : 'text-gray-500' }}">{{ number_format(round($feature->avg_latency)) }}ms</td>
                                        <td class="px-3 py-2 text-end">
                                            @if ($feature->error_count > 0)
                                                <span class="text-danger-600 font-medium">{{ $feature->error_count }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end text-xs text-gray-500">{{ number_format($feature->cached_count) }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $feature->last_used ? \Carbon\Carbon::parse($feature->last_used)->diffForHumans() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- All-Time Feature Usage --}}
            <x-filament::section heading="All-Time Feature Usage" class="mt-4">
                @if ($features['allTimeFeatureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">No all-time usage data.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Requests</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Tokens</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Raw Cost</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Total Billed</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['allTimeFeatureUsage'] as $feature)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                {{ $feature->feature_slug }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format($feature->request_count) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">{{ number_format($feature->total_tokens) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($feature->raw_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($feature->billed_cost, 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($feature->billed_cost - $feature->raw_cost, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- Store Feature Configs --}}
            <x-filament::section heading="Store Feature Configurations" class="mt-4">
                @if ($features['featureConfigs']->isEmpty())
                    <p class="text-sm text-gray-400">No custom feature configurations for this store.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">Enabled</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">Daily Limit</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.monthly_limit') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">Custom Prompt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['featureConfigs'] as $config)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ $config->featureDefinition?->name ?? $config->ai_feature_definition_id }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($config->is_enabled)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Yes</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">No</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end">{{ $config->daily_limit ?? 'Default' }}</td>
                                        <td class="px-3 py-2 text-end">{{ $config->monthly_limit ?? 'Default' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->custom_prompt_override ? 'Yes (custom)' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
