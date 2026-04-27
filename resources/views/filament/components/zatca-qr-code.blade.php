@php
    $qrData = $getRecord()->tlv_qr_base64 ?? $getRecord()->qr_code_data ?? null;
@endphp

@if ($qrData)
    <div class="flex flex-col items-center gap-3 py-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(220)
                ->errorCorrection('M')
                ->generate($qrData) !!}
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('zatca.qr_code') }} — {{ __('zatca.invoice_number') }}: {{ $getRecord()->invoice_number }}
        </p>
    </div>
@else
    <p class="text-sm text-gray-400">{{ __('zatca.qr_not_available') }}</p>
@endif
