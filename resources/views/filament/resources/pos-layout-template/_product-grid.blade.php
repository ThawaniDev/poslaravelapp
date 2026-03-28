{{-- Product grid/list partial for POS layout preview --}}
@if($productDisplay === 'list')
    <div class="space-y-1">
        @foreach(array_slice($sampleProducts, 0, 6) as $product)
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded px-3 py-2 text-xs">
                <div class="flex items-center gap-2">
                    @if($showImages)
                        <div class="w-6 h-6 rounded bg-gray-200 dark:bg-gray-700 flex-shrink-0"></div>
                    @endif
                    <span class="text-gray-800 dark:text-gray-200">{{ $product['name'] }}</span>
                </div>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $product['price'] }}</span>
            </div>
        @endforeach
    </div>
@else
    {{-- Grid display (also used for images mode) --}}
    <div class="grid gap-2" style="grid-template-columns: repeat({{ min($productColumns, 6) }}, minmax(0, 1fr));">
        @foreach(array_slice($sampleProducts, 0, min(count($sampleProducts), $productColumns * 2)) as $product)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-2 text-center">
                @if($showImages)
                    <div class="w-full aspect-square rounded bg-gray-200 dark:bg-gray-700 mb-1 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                    </div>
                @endif
                <div class="text-[10px] text-gray-800 dark:text-gray-200 truncate">{{ $product['name'] }}</div>
                <div class="text-[10px] font-bold text-blue-600 dark:text-blue-400">{{ $product['price'] }}</div>
            </div>
        @endforeach
    </div>
@endif
