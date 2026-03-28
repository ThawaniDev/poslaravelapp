<x-filament-panels::page>
    @php
        $record = $this->record;
        $header = $record->header_config ?? [];
        $body = $record->body_config ?? [];
        $footer = $record->footer_config ?? [];
        $paperPx = $record->paper_width === 80 ? 320 : 232;
        $separator = $header['separator'] ?? 'dashes';
        $separatorLine = $separator === 'dashes' ? str_repeat('- ', 20) : ($separator === 'line' ? '────────────────────────────' : '');
        $nameSize = match($header['store_name_font_size'] ?? 'large') {
            'small' => 'text-sm', 'medium' => 'text-base', default => 'text-lg'
        };
        $addrSize = match($header['address_font_size'] ?? 'small') {
            'small' => 'text-xs', 'medium' => 'text-sm', default => 'text-base'
        };
        $itemSize = match($body['item_font_size'] ?? 'medium') {
            'small' => 'text-xs', 'medium' => 'text-sm', default => 'text-base'
        };
        $colName = $body['column_widths']['name'] ?? 50;
        $colQty = $body['column_widths']['qty'] ?? 15;
        $colPrice = $body['column_widths']['price'] ?? 35;
        $priceAlign = ($body['price_alignment'] ?? 'right') === 'right' ? 'text-right' : 'text-left';
        $totalsBold = ($body['totals_bold'] ?? true) ? 'font-bold' : '';

        $sampleItems = [
            ['name' => 'Arabica Coffee Beans 250g', 'name_ar' => 'حبوب قهوة أرابيكا ٢٥٠غ', 'sku' => 'COF-001', 'qty' => 2, 'price' => 4.500],
            ['name' => 'Fresh Milk 1L', 'name_ar' => 'حليب طازج ١ لتر', 'sku' => 'MLK-010', 'qty' => 1, 'price' => 0.850],
            ['name' => 'Whole Wheat Bread', 'name_ar' => 'خبز قمح كامل', 'sku' => 'BRD-005', 'qty' => 3, 'price' => 0.350],
        ];
        $subtotal = collect($sampleItems)->sum(fn($i) => $i['qty'] * $i['price']);
        $vat = round($subtotal * 0.05, 3);
        $total = $subtotal + $vat;
    @endphp

    <div class="space-y-6">
        {{-- Template Info Bar --}}
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $record->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->name_ar }}</p>
                </div>
                <div class="flex gap-2">
                    <x-filament::badge :color="$record->paper_width === 80 ? 'success' : 'warning'">
                        {{ $record->paper_width }}mm
                    </x-filament::badge>
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                    @if($record->show_bilingual)
                        <x-filament::badge color="info">{{ __('ui.bilingual') }}</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Receipt Preview --}}
        <div class="flex justify-center">
            <div class="bg-white shadow-xl rounded-sm border border-gray-300"
                 style="width: {{ $paperPx }}px; font-family: 'Courier New', monospace; padding: 16px 12px;">

                {{-- ═══ HEADER ═══ --}}
                <div class="text-center mb-2">
                    {{-- Logo placeholder --}}
                    @if(($header['logo_max_height_px'] ?? 60) > 0)
                        <div class="mx-auto mb-2 bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center"
                             style="width: 80px; height: {{ $header['logo_max_height_px'] ?? 60 }}px;">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                            </svg>
                        </div>
                    @endif

                    {{-- Store name --}}
                    <div class="{{ $nameSize }} {{ ($header['store_name_bold'] ?? true) ? 'font-bold' : '' }} text-black">
                        Thawani Store
                    </div>
                    @if($record->show_bilingual)
                        <div class="{{ $nameSize }} {{ ($header['store_name_bold'] ?? true) ? 'font-bold' : '' }} text-black" dir="rtl">
                            متجر ثواني
                        </div>
                    @endif

                    {{-- Address --}}
                    <div class="{{ $addrSize }} text-gray-700 mt-1">
                        123 Main Street, Muscat
                    </div>
                    @if($record->show_bilingual)
                        <div class="{{ $addrSize }} text-gray-700" dir="rtl">
                            ١٢٣ الشارع الرئيسي، مسقط
                        </div>
                    @endif

                    {{-- VAT Number --}}
                    @if($header['show_vat_number'] ?? true)
                        <div class="text-xs text-gray-600 mt-1">VAT: 123456789012345</div>
                    @endif

                    {{-- ZATCA QR in header --}}
                    @if(($record->zatca_qr_position ?? 'footer') === 'header')
                        <div class="mx-auto my-2 bg-gray-200 border border-gray-300 flex items-center justify-center"
                             style="width: {{ $footer['zatca_qr_size_px'] ?? 120 }}px; height: {{ $footer['zatca_qr_size_px'] ?? 120 }}px;">
                            <span class="text-xs text-gray-500">ZATCA QR</span>
                        </div>
                    @endif
                </div>

                {{-- Header separator --}}
                @if($separator !== 'none')
                    <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">
                        {{ $separatorLine }}
                    </div>
                @endif

                {{-- ═══ BODY ═══ --}}
                <div class="my-2">
                    {{-- Column headers --}}
                    <div class="flex {{ $itemSize }} font-bold text-black border-b border-gray-300 pb-1 mb-1">
                        <div style="width:{{ $colName }}%">Item</div>
                        <div style="width:{{ $colQty }}%" class="text-center">Qty</div>
                        <div style="width:{{ $colPrice }}%" class="{{ $priceAlign }}">Total</div>
                    </div>

                    {{-- Items --}}
                    @foreach($sampleItems as $item)
                        <div class="mb-1">
                            <div class="flex {{ $itemSize }} text-black">
                                <div style="width:{{ $colName }}%" class="truncate">{{ $item['name'] }}</div>
                                <div style="width:{{ $colQty }}%" class="text-center">{{ $item['qty'] }}</div>
                                <div style="width:{{ $colPrice }}%" class="{{ $priceAlign }}">{{ number_format($item['qty'] * $item['price'], 3) }}</div>
                            </div>
                            @if($record->show_bilingual)
                                <div class="{{ $itemSize }} text-gray-600 truncate" dir="rtl" style="font-size: 0.65rem;">{{ $item['name_ar'] }}</div>
                            @endif
                            @if($body['show_sku'] ?? false)
                                <div class="text-xs text-gray-500">SKU: {{ $item['sku'] }}</div>
                            @endif
                            @if(($body['row_separator'] ?? 'none') === 'line')
                                <div class="border-b border-dotted border-gray-300 my-0.5"></div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Totals --}}
                    @if($separator !== 'none')
                        <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">
                            {{ $separatorLine }}
                        </div>
                    @endif

                    <div class="space-y-0.5 {{ $itemSize }} text-black">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>{{ number_format($subtotal, 3) }} SAR</span>
                        </div>
                        <div class="flex justify-between">
                            <span>VAT (5%)</span>
                            <span>{{ number_format($vat, 3) }} SAR</span>
                        </div>
                        <div class="flex justify-between {{ $totalsBold }} text-black border-t border-gray-400 pt-1 mt-1">
                            <span>TOTAL</span>
                            <span>{{ number_format($total, 3) }} SAR</span>
                        </div>
                    </div>
                </div>

                {{-- ═══ FOOTER ═══ --}}
                @if($separator !== 'none')
                    <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">
                        {{ $separatorLine }}
                    </div>
                @endif

                <div class="text-center mt-2">
                    @if($footer['show_receipt_number'] ?? true)
                        <div class="text-xs text-gray-600">Receipt #: INV-2025-00142</div>
                    @endif
                    <div class="text-xs text-gray-600">{{ now()->format('d/m/Y H:i:s') }}</div>
                    @if($footer['show_cashier_name'] ?? true)
                        <div class="text-xs text-gray-600">Cashier: Ahmed</div>
                    @endif

                    {{-- ZATCA QR in footer --}}
                    @if(($record->zatca_qr_position ?? 'footer') === 'footer')
                        <div class="mx-auto my-3 bg-gray-200 border border-gray-300 flex items-center justify-center"
                             style="width: {{ $footer['zatca_qr_size_px'] ?? 120 }}px; height: {{ $footer['zatca_qr_size_px'] ?? 120 }}px;">
                            <div class="text-center">
                                <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z" />
                                </svg>
                                <span class="text-xs text-gray-500">ZATCA QR</span>
                            </div>
                        </div>
                    @endif

                    {{-- Thank you message --}}
                    <div class="text-sm text-black mt-2">{{ $footer['thank_you_en'] ?? 'Thank you!' }}</div>
                    @if($record->show_bilingual)
                        <div class="text-sm text-black" dir="rtl">{{ $footer['thank_you_ar'] ?? 'شكراً لزيارتكم' }}</div>
                    @endif

                    {{-- Custom footer --}}
                    @if(!empty($footer['custom_footer_text']))
                        <div class="text-xs text-gray-500 mt-2">{{ $footer['custom_footer_text'] }}</div>
                    @endif
                    @if($record->show_bilingual && !empty($footer['custom_footer_text_ar']))
                        <div class="text-xs text-gray-500" dir="rtl">{{ $footer['custom_footer_text_ar'] }}</div>
                    @endif

                    @if($footer['show_social_handles'] ?? false)
                        <div class="text-xs text-gray-500 mt-1">@ThawaniStore | fb.com/thawani</div>
                    @endif
                </div>

                {{-- Paper cut line --}}
                <div class="mt-4 border-t-2 border-dashed border-gray-300"></div>
            </div>
        </div>

        {{-- Configuration Summary --}}
        <x-filament::section heading="{{ __('ui.configuration_summary') }}" collapsible collapsed>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Header Config --}}
                <div>
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">{{ __('ui.header_design') }}</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.logo_max_height') }}</dt><dd>{{ $header['logo_max_height_px'] ?? 60 }}px</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.store_name_font_size') }}</dt><dd>{{ $header['store_name_font_size'] ?? 'large' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.store_name_bold') }}</dt><dd>{{ ($header['store_name_bold'] ?? true) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.separator_style') }}</dt><dd>{{ $header['separator'] ?? 'dashes' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_vat_number') }}</dt><dd>{{ ($header['show_vat_number'] ?? true) ? '✓' : '✗' }}</dd></div>
                    </dl>
                </div>

                {{-- Body Config --}}
                <div>
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">{{ __('ui.body_design') }}</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.item_font_size') }}</dt><dd>{{ $body['item_font_size'] ?? 'medium' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.price_alignment') }}</dt><dd>{{ $body['price_alignment'] ?? 'right' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_sku') }}</dt><dd>{{ ($body['show_sku'] ?? false) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_barcode') }}</dt><dd>{{ ($body['show_barcode'] ?? false) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.totals_bold') }}</dt><dd>{{ ($body['totals_bold'] ?? true) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Columns</dt><dd>{{ $colName }}/{{ $colQty }}/{{ $colPrice }}%</dd></div>
                    </dl>
                </div>

                {{-- Footer Config --}}
                <div>
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">{{ __('ui.footer_design') }}</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.zatca_qr_size') }}</dt><dd>{{ $footer['zatca_qr_size_px'] ?? 120 }}px</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.zatca_qr_position') }}</dt><dd>{{ $record->zatca_qr_position ?? 'footer' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_receipt_number') }}</dt><dd>{{ ($footer['show_receipt_number'] ?? true) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_cashier_name') }}</dt><dd>{{ ($footer['show_cashier_name'] ?? true) ? '✓' : '✗' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.show_social_handles') }}</dt><dd>{{ ($footer['show_social_handles'] ?? false) ? '✓' : '✗' }}</dd></div>
                    </dl>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
