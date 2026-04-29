@php
    $qrData = trim((string) ($getRecord()->tlv_qr_base64 ?? $getRecord()->qr_code_data ?? ''));
    $qrPngBase64 = null;
    $canRenderPng = extension_loaded('imagick');

    if ($qrData !== '' && $canRenderPng) {
        try {
            // Render as PNG with explicit quiet-zone margin for better
            // scanner interoperability (including QRKSAReader app).
            $qrPngBase64 = base64_encode(
                \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                    ->size(520)
                    ->margin(4)
                    ->errorCorrection('M')
                    ->generate($qrData)
            );
        } catch (\Throwable $e) {
            $qrPngBase64 = null;
        }
    }
@endphp

@if ($qrData !== '')
    <div class="flex flex-col items-center gap-3 py-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @if ($qrPngBase64)
                <img
                    src="data:image/png;base64,{{ $qrPngBase64 }}"
                    alt="ZATCA QR"
                    class="h-[320px] w-[320px]"
                    style="image-rendering: pixelated;"
                >
            @else
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size(360)
                    ->margin(4)
                    ->errorCorrection('M')
                    ->generate($qrData) !!}
            @endif
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('zatca.qr_code') }} - {{ __('zatca.invoice_number') }}: {{ $getRecord()->invoice_number }}
        </p>
    </div>
@else
    <p class="text-sm text-gray-400">{{ __('zatca.qr_not_available') }}</p>
@endif
