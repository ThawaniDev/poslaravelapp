<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4 flex gap-2">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    @php
        $cert = $this->getCurrentCertificate();
        $device = $this->getCurrentDevice();
    @endphp

    @if ($cert || $device)
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            @if ($cert)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">{{ __('zatca.current_certificate') }}</h3>
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.certificate_type') }}</dt><dd>{{ __('zatca.cert_type_' . ($cert->certificate_type?->value ?? '')) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.status') }}</dt><dd>{{ __('zatca.cert_status_' . ($cert->status?->value ?? '')) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.ccsid') }}</dt><dd class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($cert->ccsid, 24) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.issued_at') }}</dt><dd>{{ optional($cert->issued_at)->format('Y-m-d') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.expires_at') }}</dt><dd>{{ optional($cert->expires_at)->format('Y-m-d') ?? '—' }}</dd></div>
                    </dl>
                </div>
            @endif

            @if ($device)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">{{ __('zatca.current_device') }}</h3>
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.uuid') }}</dt><dd class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($device->device_uuid, 24) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.environment') }}</dt><dd>{{ __('zatca.env_' . ($device->environment ?? 'sandbox')) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.status') }}</dt><dd>{{ __('zatca.device_status_' . ($device->status?->value ?? '')) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.icv') }}</dt><dd>{{ $device->current_icv ?? 0 }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('zatca.is_tampered') }}</dt><dd>{{ $device->is_tampered ? '⚠️' : '✓' }}</dd></div>
                        @if ($device->activation_code && ! $device->activated_at)
                            <div class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded">
                                <div class="text-xs text-gray-500">{{ __('zatca.activation_code') }}</div>
                                <div class="font-mono text-sm">{{ $device->activation_code }}</div>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
