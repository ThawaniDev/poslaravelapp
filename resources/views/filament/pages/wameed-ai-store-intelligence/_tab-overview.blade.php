            {{-- Primary KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_requests') }}</p>
                        <p class="text-3xl font-bold text-primary-600">{{ number_format($detail['totalRequests']) }}</p>
                        <p class="text-xs text-gray-400">Today: {{ number_format($detail['todayRequests']) }} · Last {{ $dateRange }}d: {{ number_format($detail['recentRequests']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.raw_cost_all_time') }}</p>
                        <p class="text-3xl font-bold text-warning-600">${{ number_format($detail['totalRawCost'], 4) }}</p>
                        <p class="text-xs text-gray-400">Today: ${{ number_format($detail['todayRawCost'], 4) }} · Last {{ $dateRange }}d: ${{ number_format($detail['recentRawCost'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.billed_cost_all_time') }}</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($detail['totalBilledCost'], 4) }}</p>
                        <p class="text-xs text-gray-400">Today: ${{ number_format($detail['todayBilledCost'], 4) }} · Last {{ $dateRange }}d: ${{ number_format($detail['recentBilledCost'], 4) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.platform_margin') }}</p>
                        <p class="text-3xl font-bold text-success-600">${{ number_format($detail['totalMargin'], 4) }}</p>
                        @php $marginPct = $detail['totalRawCost'] > 0 ? round($detail['totalMargin'] / $detail['totalRawCost'] * 100, 1) : 0; @endphp
                        <p class="text-xs text-gray-400">{{ $marginPct }}% markup</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Secondary KPIs --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5 mt-4">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_tokens') }}</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($detail['totalTokens']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.avg_latency') }}</p>
                        <p class="text-2xl font-bold {{ $detail['avgLatency'] > 5000 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($detail['avgLatency']) }}ms</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.cache_hit_rate') }}</p>
                        <p class="text-2xl font-bold text-info-600">{{ $detail['cacheHitRate'] }}%</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.error_rate') }}</p>
                        <p class="text-2xl font-bold {{ $detail['errorRate'] > 5 ? 'text-danger-600' : 'text-success-600' }}">{{ $detail['errorRate'] }}%</p>
                        <p class="text-xs text-gray-400">{{ number_format($detail['errorCount']) }} {{ __('ai.errors') }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.chats') }}</p>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($detail['totalChats']) }}</p>
                        <p class="text-xs text-gray-400">{{ number_format($detail['totalChatMessages']) }} {{ __('ai.messages') }}</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Store Info Card --}}
            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                <x-filament::section :heading="__('ai.section_store_information')">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.store_name') }}</span>
                            <p class="font-medium">{{ $detail['store']->name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.arabic_name') }}</span>
                            <p class="font-medium">{{ $detail['store']->name_ar ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.col_slug') }}</span>
                            <p class="font-mono text-xs">{{ $detail['store']->slug }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.business_type') }}</span>
                            <p class="font-medium">{{ $detail['store']->business_type ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.store_active') }}</span>
                            <p>
                                @if ($detail['store']->is_active)
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.active') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.inactive') }}</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.store_created') }}</span>
                            <p class="text-xs">{{ $detail['store']->created_at?->format('M d, Y') ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.first_ai_use') }}</span>
                            <p class="text-xs">{{ $detail['firstActivity'] ? \Carbon\Carbon::parse($detail['firstActivity'])->format('M d, Y H:i') : __('ai.never') }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('ai.last_ai_use') }}</span>
                            <p class="text-xs">{{ $detail['lastActivity'] ? \Carbon\Carbon::parse($detail['lastActivity'])->diffForHumans() . ' (' . \Carbon\Carbon::parse($detail['lastActivity'])->format('M d, Y H:i') . ')' : __('ai.never') }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section :heading="__('ai.section_billing_configuration')">
                    @if ($detail['billingConfig'])
                        @php $bc = $detail['billingConfig']; @endphp
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.ai_status') }}</span>
                                <p>
                                    @if ($bc->is_ai_enabled)
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ __('ai.enabled') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ __('ai.ai_disabled') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.monthly_limit') }}</span>
                                <p class="font-medium">{{ $bc->monthly_limit_usd > 0 ? '$' . number_format($bc->monthly_limit_usd, 2) : __('ai.no_limit') }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.custom_margin_pct') }}</span>
                                <p class="font-medium">{{ $bc->custom_margin_percentage !== null ? $bc->custom_margin_percentage . '%' : __('ai.platform_default') }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.disabled_reason') }}</span>
                                <p class="text-xs">{{ $bc->disabled_reason ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.enabled_at') }}</span>
                                <p class="text-xs">{{ $bc->enabled_at ? \Carbon\Carbon::parse($bc->enabled_at)->format('M d, Y H:i') : '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.disabled_at') }}</span>
                                <p class="text-xs">{{ $bc->disabled_at ? \Carbon\Carbon::parse($bc->disabled_at)->format('M d, Y H:i') : '—' }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('ai.notes') }}</span>
                                <p class="text-xs">{{ $bc->notes ?? '—' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">{{ __('ai.no_billing_config_store') }}</p>
                    @endif
                </x-filament::section>
            </div>
