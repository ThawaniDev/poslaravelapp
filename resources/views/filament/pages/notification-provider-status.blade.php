<x-filament-panels::page>
    <div class="space-y-4">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('notifications.provider_status_description') }}
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
