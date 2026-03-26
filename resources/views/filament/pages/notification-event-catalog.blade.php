<x-filament-panels::page>
    <div class="space-y-8">
        @foreach($catalog as $categoryKey => $category)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $category['label'] }}</h2>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($category['events'] as $eventKey => $event)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <code class="text-sm font-mono text-primary-600 dark:text-primary-400">{{ $eventKey }}</code>
                                        @if($event['is_critical'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                {{ __('notifications.critical') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $event['description'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        {{ __('notifications.default_recipients') }}: {{ $event['default_recipients'] }}
                                    </p>
                                </div>
                            </div>

                            {{-- Variables --}}
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($event['variables'] as $var)
                                    <code class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        @{{ {{ $var }} }}
                                    </code>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
