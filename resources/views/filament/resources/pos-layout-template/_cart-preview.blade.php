{{-- Cart preview partial for POS layout preview --}}
<div class="flex flex-col h-full">
    <div class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">
        <span class="flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
            Cart (3 items)
        </span>
    </div>

    <div class="flex-1 space-y-1 overflow-hidden">
        <div class="flex justify-between text-[10px] py-1 border-b dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">2× Cappuccino</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">3.600</span>
        </div>
        <div class="flex justify-between text-[10px] py-1 border-b dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">1× Croissant</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">0.650</span>
        </div>
        <div class="flex justify-between text-[10px] py-1 border-b dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">3× Water</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">0.600</span>
        </div>
    </div>

    <div class="mt-2 pt-2 border-t dark:border-gray-700">
        <div class="flex justify-between text-xs">
            <span class="text-gray-500">Subtotal</span>
            <span>4.850</span>
        </div>
        <div class="flex justify-between text-xs">
            <span class="text-gray-500">VAT</span>
            <span>0.243</span>
        </div>
        <div class="flex justify-between text-sm font-bold mt-1 pt-1 border-t dark:border-gray-700 text-gray-900 dark:text-white">
            <span>Total</span>
            <span>5.093 SAR</span>
        </div>
    </div>

    {{-- Payment buttons --}}
    @if(!empty($paymentButtons))
        <div class="mt-2 grid grid-cols-2 gap-1">
            @foreach(array_slice($paymentButtons, 0, 4) as $btn)
                <div class="text-[9px] text-center py-1 rounded bg-blue-500 text-white truncate px-1">{{ $btn }}</div>
            @endforeach
        </div>
    @else
        <div class="mt-2 grid grid-cols-2 gap-1">
            <div class="text-[9px] text-center py-1 rounded bg-blue-500 text-white">Cash</div>
            <div class="text-[9px] text-center py-1 rounded bg-green-500 text-white">Card</div>
        </div>
    @endif
</div>
