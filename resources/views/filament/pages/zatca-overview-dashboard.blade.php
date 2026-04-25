@php
    /** @var array<string,mixed> $totals */
    /** @var array<int,array<string,mixed>> $stores */
@endphp

<x-filament-panels::page>
    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.stores') ?: 'Stores' }}</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($totals['stores']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.connected') ?: 'Connected' }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($totals['connected']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.healthy') ?: 'Healthy' }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($totals['healthy']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.tampered') ?: 'Tampered' }}</p>
                <p class="text-3xl font-bold {{ ($totals['tampered'] ?? 0) > 0 ? 'text-danger-600' : 'text-gray-600' }}">{{ number_format($totals['tampered']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.invoices') ?: 'Invoices' }}</p>
                <p class="text-3xl font-bold text-info-600">{{ number_format($totals['invoices']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.accepted') ?: 'Accepted' }}</p>
                <p class="text-3xl font-bold text-success-600">{{ number_format($totals['accepted']) }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('zatca.rejected') ?: 'Rejected' }}</p>
                <p class="text-3xl font-bold {{ ($totals['rejected'] ?? 0) > 0 ? 'text-danger-600' : 'text-gray-600' }}">{{ number_format($totals['rejected']) }}</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Per-Store Table --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('zatca.stores_breakdown') ?: 'Stores' }}</x-slot>

        @if (empty($stores))
            <p class="text-gray-500 text-center py-8">{{ __('zatca.no_stores') ?: 'No stores found.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="text-left px-3 py-2">{{ __('zatca.store') ?: 'Store' }}</th>
                            <th class="text-left px-3 py-2">{{ __('zatca.environment') ?: 'Env' }}</th>
                            <th class="text-center px-3 py-2">{{ __('zatca.connected') ?: 'Connected' }}</th>
                            <th class="text-center px-3 py-2">{{ __('zatca.healthy') ?: 'Healthy' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.queue') ?: 'Queue' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.invoices') ?: 'Invoices' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.accepted') ?: 'Accepted' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.rejected') ?: 'Rejected' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.success_rate') ?: 'Success %' }}</th>
                            <th class="text-right px-3 py-2">{{ __('zatca.cert_expiry') ?: 'Cert Expiry' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($stores as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $row['store_name'] }}</td>
                                <td class="px-3 py-2"><x-filament::badge color="gray">{{ $row['environment'] ?? '—' }}</x-filament::badge></td>
                                <td class="px-3 py-2 text-center">
                                    @if ($row['connected'] ?? false)
                                        <x-filament::badge color="success">✓</x-filament::badge>
                                    @else
                                        <x-filament::badge color="danger">✗</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($row['is_healthy'] ?? false)
                                        <x-filament::badge color="success">✓</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">!</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['queue_depth'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['total_invoices'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right text-success-600">{{ number_format($row['accepted'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ number_format($row['rejected'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right">{{ ($row['success_rate'] ?? 0) }}%</td>
                                <td class="px-3 py-2 text-right">
                                    @if (isset($row['days_until_expiry']))
                                        @php $d = (int) $row['days_until_expiry']; @endphp
                                        <span class="{{ $d < 30 ? 'text-danger-600' : ($d < 90 ? 'text-warning-600' : 'text-gray-600') }}">{{ $d }}d</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
