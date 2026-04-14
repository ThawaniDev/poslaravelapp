            <x-filament::section heading="Recent Usage Logs ({{ number_format($logData['totalLogs']) }} total)">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Time</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Feature</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">Model</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">In Tokens</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Out Tokens</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Raw Cost</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Billed</th>
                                <th class="px-3 py-2 text-end font-medium text-gray-500">Latency</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500">Cached</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logData['logs'] as $log)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $log->created_at->format('M d H:i:s') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                            {{ $log->feature_slug }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $log->model_used ?? '—' }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($log->input_tokens) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($log->output_tokens) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs">${{ number_format($log->estimated_cost_usd, 6) }}</td>
                                    <td class="px-3 py-2 text-end font-mono text-xs font-medium">${{ number_format($log->billed_cost_usd ?? $log->estimated_cost_usd, 6) }}</td>
                                    <td class="px-3 py-2 text-end text-xs {{ ($log->latency_ms ?? 0) > 5000 ? 'text-danger-600' : 'text-gray-500' }}">{{ number_format($log->latency_ms ?? 0) }}ms</td>
                                    <td class="px-3 py-2 text-center">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $log->status === 'success' || $log->status?->value === 'success',
                                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $log->status === 'error' || $log->status?->value === 'error',
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => ! in_array(is_object($log->status) ? $log->status->value : $log->status, ['success', 'error']),
                                        ])>{{ is_object($log->status) ? $log->status->value : $log->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs">
                                        @if ($log->response_cached)
                                            <span class="text-info-600">✓</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($log->error_message)
                                    <tr class="bg-danger-50 dark:bg-danger-500/5">
                                        <td colspan="10" class="px-3 py-1 text-xs text-danger-600">
                                            Error: {{ $log->error_message }}
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="10" class="px-3 py-8 text-center text-gray-400">No usage logs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if ($logData['totalPages'] > 1)
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500">Page {{ $logPage }} of {{ $logData['totalPages'] }} ({{ number_format($logData['totalLogs']) }} total)</p>
                        <div class="flex items-center gap-2">
                            @if ($logPage > 1)
                                <x-filament::button wire:click="prevLogPage" size="xs" color="gray">← Previous</x-filament::button>
                            @endif
                            @if ($logPage < $logData['totalPages'])
                                <x-filament::button wire:click="nextLogPage" size="xs" color="gray">Next →</x-filament::button>
                            @endif
                        </div>
                    </div>
                @endif
            </x-filament::section>
