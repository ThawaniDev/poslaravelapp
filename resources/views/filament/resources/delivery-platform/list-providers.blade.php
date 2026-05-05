<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            @if($record->logo_url)
                <img src="{{ $record->logo_url }}" alt="{{ $record->name }}" class="w-12 h-12 rounded-full object-cover">
            @else
                <div class="w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                    <x-heroicon-o-truck class="w-6 h-6 text-primary-600 dark:text-primary-400"/>
                </div>
            @endif
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $record->name }}</h2>
                @if($record->name_ar)
                    <p class="text-sm text-gray-500">{{ $record->name_ar }}</p>
                @endif
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $record->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                    {{ $record->slug }}
                </span>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
