        <x-filament::section :heading="__('ai.invoices')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.invoice_number') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.store') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.period') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.field_messages_sent') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.field_raw_cost_openai') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_percentage') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.margin_dollar') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500">{{ __('ai.field_billed_cost') }}</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.status') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('ai.due_date') }}</th>
                            @if ($canManage)
                                <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('ai.actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-2 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                                <td class="px-3 py-2">{{ $invoice->store?->name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $invoice->year }}-{{ str_pad($invoice->month, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-2 text-end">{{ number_format($invoice->total_requests) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-gray-500">${{ number_format($invoice->raw_cost_usd, 4) }}</td>
                                <td class="px-3 py-2 text-end font-mono text-xs">{{ number_format($invoice->margin_percentage, 1) }}%</td>
                                <td class="px-3 py-2 text-end font-mono text-xs text-success-600">${{ number_format($invoice->margin_amount_usd, 4) }}</td>
                                <td class="px-3 py-2 text-end font-medium">${{ number_format($invoice->billed_amount_usd, 4) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $invoice->status === 'paid',
                                        'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $invoice->status === 'pending',
                                        'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $invoice->status === 'overdue',
                                    ])>{{ ucfirst($invoice->status) }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-center">
                                        @if ($invoice->status === 'pending')
                                            <div class="flex items-center justify-center gap-1">
                                                <button wire:click="startMarkPaid('{{ $invoice->id }}')" class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __('ai.pay') }}</button>
                                                <span class="text-gray-300">|</span>
                                                <button wire:click="markInvoiceOverdue('{{ $invoice->id }}')" class="text-xs text-danger-600 hover:text-danger-800 font-medium">{{ __('ai.overdue') }}</button>
                                            </div>
                                        @elseif ($invoice->status === 'overdue')
                                            <button wire:click="startMarkPaid('{{ $invoice->id }}')" class="text-xs text-success-600 hover:text-success-800 font-medium">{{ __('ai.mark_paid') }}</button>
                                        @elseif ($invoice->status === 'paid')
                                            <span class="text-xs text-gray-400">{{ $invoice->paid_at?->format('M d') }}</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>

                            {{-- Mark Paid Form --}}
                            @if ($markingInvoiceId === $invoice->id)
                                <tr class="bg-success-50 dark:bg-success-500/5">
                                    <td colspan="{{ $canManage ? 11 : 10 }}" class="px-3 py-3">
                                        <div class="flex items-end gap-3">
                                            <div class="flex-1">
                                                <label class="text-xs font-medium text-gray-600">{{ __('ai.payment_reference') }}</label>
                                                <input wire:model="paymentReference" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('ai.placeholder_bank_transfer') }}">
                                            </div>
                                            <div class="flex-1">
                                                <label class="text-xs font-medium text-gray-600">{{ __('ai.notes') }}</label>
                                                <input wire:model="paymentNotes" type="text" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" placeholder="{{ __('ai.optional_notes') }}">
                                            </div>
                                            <x-filament::button wire:click="markInvoicePaid" size="sm" color="success">{{ __('ai.confirm_paid') }}</x-filament::button>
                                            <x-filament::button wire:click="cancelMarkPaid" size="sm" color="gray">{{ __('ai.cancel') }}</x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 11 : 10 }}" class="px-3 py-8 text-center text-gray-400">{{ __('ai.no_invoices_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
