<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('settings.zatca_environment') }}</x-slot>
        <x-slot name="description">{{ __('settings.zatca_env_readonly_notice') }}</x-slot>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.environment') }}</dt>
                <dd class="mt-1 font-medium">
                    @php $env = config('zatca.environment', 'sandbox'); @endphp
                    <x-filament::badge :color="match($env) {
                        'production' => 'success',
                        'simulation' => 'warning',
                        default => 'gray',
                    }">
                        {{ __('settings.' . $env) }}
                    </x-filament::badge>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.api_base_url') }}</dt>
                <dd class="mt-1 font-mono break-all">{{ config('zatca.api_url') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('zatca.csr_template') }}</dt>
                <dd class="mt-1 font-mono">{{ config('zatca.csr_template') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('zatca.env_key') }}</dt>
                <dd class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-300">
                    ZATCA_ENVIRONMENT=<span class="font-semibold">{{ $env }}</span><br>
                    ZATCA_API_URL=<span class="font-semibold">{{ config('zatca.api_url') }}</span>
                </dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
