<div class="space-y-6">
    {{-- English Preview --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">{{ __('notifications.preview_english') }}</h3>
        <div class="space-y-2">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                    <x-heroicon-s-bell class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $previewEn['title'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-line">{{ $previewEn['body'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Arabic Preview --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4" dir="rtl">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">{{ __('notifications.preview_arabic') }}</h3>
        <div class="space-y-2">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                    <x-heroicon-s-bell class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $previewAr['title'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-line">{{ $previewAr['body'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Template Variables --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">{{ __('notifications.sample_data_used') }}</h3>
        <div class="grid grid-cols-2 gap-2">
            @foreach($previewEn['sample_data'] as $var => $val)
                <div class="flex items-center gap-2 text-xs">
                    <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-mono">@{{ {{ $var }} }}</code>
                    <span class="text-gray-500 dark:text-gray-400">→</span>
                    <span class="text-gray-900 dark:text-white truncate">{{ $val }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Meta Info --}}
    <div class="flex items-center justify-between text-xs text-gray-400">
        <span>{{ __('notifications.channel') }}: {{ $template->channel->value }}</span>
        <span>{{ __('notifications.event_key') }}: {{ $template->event_key }}</span>
    </div>
</div>
