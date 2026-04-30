            {{-- Models Used --}}
            <x-filament::section :heading="__('ai.section_models_used_last_days', ['days' => $dateRange])">
                @if ($features['modelsUsed']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_model_usage') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_model') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_requests') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_tokens') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_raw_cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['modelsUsed'] as $model)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $model->model_used ?? __('ai.unknown') }}</span>
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
            <x-filament::section :heading="__('ai.section_feature_usage_last_days', ['days' => $dateRange])" class="mt-4">
                @if ($features['featureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_feature_usage_period') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_feature') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_requests') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_tokens') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_raw_cost') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_billed') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.avg_latency') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.errors') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_cached') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.last_ai_use') }}</th>
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
            <x-filament::section :heading="__('ai.section_alltime_feature_usage')" class="mt-4">
                @if ($features['allTimeFeatureUsage']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_alltime_usage') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_feature') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_total_requests') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_total_tokens') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_total_raw_cost') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_total_billed') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_margin') }}</th>
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
            <x-filament::section :heading="__('ai.section_store_feature_configs')" class="mt-4">
                @if ($features['featureConfigs']->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('ai.no_feature_configs') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_feature') }}</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.col_enabled') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.col_daily_limit') }}</th>
                                    <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.monthly_limit') }}</th>
                                    <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.col_custom_prompt') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features['featureConfigs'] as $config)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-medium">{{ $config->featureDefinition?->name ?? $config->ai_feature_definition_id }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($config->is_enabled)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.yes') }}</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.no_text') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end">{{ $config->daily_limit ?? __('ai.default_limit') }}</td>
                                        <td class="px-3 py-2 text-end">{{ $config->monthly_limit ?? __('ai.default_limit') }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500 max-w-48 truncate">{{ $config->custom_prompt_override ? __('ai.yes_custom') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
