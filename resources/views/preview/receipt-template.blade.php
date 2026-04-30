<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $record->name }} — Receipt Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f3f4f6; margin: 0; padding: 24px 16px; }
        @media (prefers-color-scheme: dark) {
            body { background: #1f2937; }
            .info-bar { background: #374151 !important; color: #e5e7eb !important; }
            .info-bar .subtitle { color: #9ca3af !important; }
        }
    </style>
</head>
<body>
@php
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

<div style="max-width: 640px; margin: 0 auto;">
    {{-- Template Info Bar --}}
    <div class="info-bar" style="background:#fff; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h3 style="font-size:18px; font-weight:600; margin:0;">{{ e($record->name) }}</h3>
                <p class="subtitle" style="font-size:14px; color:#6b7280; margin:4px 0 0;">{{ e($record->name_ar) }}</p>
            </div>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <span style="display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:500; background:{{ $record->paper_width === 80 ? '#dcfce7' : '#fef9c3' }}; color:{{ $record->paper_width === 80 ? '#166534' : '#854d0e' }};">
                    {{ $record->paper_width }}mm
                </span>
                <span style="display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:500; background:{{ $record->is_active ? '#dcfce7' : '#fee2e2' }}; color:{{ $record->is_active ? '#166534' : '#991b1b' }};">
                    {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                </span>
                @if($record->show_bilingual)
                    <span style="display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:500; background:#dbeafe; color:#1e40af;">{{ __('ui.bilingual') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Receipt Preview --}}
    <div style="display:flex; justify-content:center;">
        <div class="bg-white shadow-xl rounded-sm border border-gray-300"
             style="width: {{ $paperPx }}px; font-family: 'Courier New', monospace; padding: 16px 12px;">

            {{-- ═══ HEADER ═══ --}}
            <div class="text-center mb-2">
                @if(($header['logo_max_height_px'] ?? 60) > 0)
                    <div class="mx-auto mb-2 bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center"
                         style="width: 80px; height: {{ $header['logo_max_height_px'] ?? 60 }}px;">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                        </svg>
                    </div>
                @endif

                <div class="{{ $nameSize }} {{ ($header['store_name_bold'] ?? true) ? 'font-bold' : '' }} text-black">
                    Wameed Store
                </div>
                @if($record->show_bilingual)
                    <div class="{{ $nameSize }} {{ ($header['store_name_bold'] ?? true) ? 'font-bold' : '' }} text-black" dir="rtl">
                        متجر وميض
                    </div>
                @endif

                <div class="{{ $addrSize }} text-gray-700 mt-1">123 Main Street, Muscat</div>
                @if($record->show_bilingual)
                    <div class="{{ $addrSize }} text-gray-700" dir="rtl">١٢٣ الشارع الرئيسي، مسقط</div>
                @endif

                @if($header['show_vat_number'] ?? true)
                    <div class="text-xs text-gray-600 mt-1">VAT: 123456789012345</div>
                @endif

                @if(($record->zatca_qr_position ?? 'footer') === 'header')
                    <div class="mx-auto my-2 bg-gray-200 border border-gray-300 flex items-center justify-center"
                         style="width: {{ $footer['zatca_qr_size_px'] ?? 120 }}px; height: {{ $footer['zatca_qr_size_px'] ?? 120 }}px;">
                        <span class="text-xs text-gray-500">{{ __('ui.zatca_qr_label') }}</span>
                    </div>
                @endif
            </div>

            @if($separator !== 'none')
                <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">{{ $separatorLine }}</div>
            @endif

            {{-- ═══ BODY ═══ --}}
            <div class="my-2">
                <div class="flex {{ $itemSize }} font-bold text-black border-b border-gray-300 pb-1 mb-1">
                    <div style="width:{{ $colName }}%">{{ __('ui.col_item') }}</div>
                    <div style="width:{{ $colQty }}%" class="text-center">{{ __('ui.col_qty') }}</div>
                    <div style="width:{{ $colPrice }}%" class="{{ $priceAlign }}">{{ __('ui.col_total') }}</div>
                </div>

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

                @if($separator !== 'none')
                    <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">{{ $separatorLine }}</div>
                @endif

                <div class="space-y-0.5 {{ $itemSize }} text-black">
                    <div class="flex justify-between"><span>{{ __('ui.subtotal') }}</span><span>{{ number_format($subtotal, 3) }} SAR</span></div>
                    <div class="flex justify-between"><span>{{ __('ui.col_vat_5') }}</span><span>{{ number_format($vat, 3) }} SAR</span></div>
                    <div class="flex justify-between {{ $totalsBold }} text-black border-t border-gray-400 pt-1 mt-1">
                        <span>{{ __('ui.col_total_row') }}</span><span>{{ number_format($total, 3) }} SAR</span>
                    </div>
                </div>
            </div>

            {{-- ═══ FOOTER ═══ --}}
            @if($separator !== 'none')
                <div class="text-center text-gray-400 text-xs tracking-widest my-1 overflow-hidden whitespace-nowrap">{{ $separatorLine }}</div>
            @endif

            <div class="text-center mt-2">
                @if($footer['show_receipt_number'] ?? true)
                    <div class="text-xs text-gray-600">Receipt #: INV-2025-00142</div>
                @endif
                <div class="text-xs text-gray-600">{{ now()->format('d/m/Y H:i:s') }}</div>
                @if($footer['show_cashier_name'] ?? true)
                    <div class="text-xs text-gray-600">Cashier: Ahmed</div>
                @endif

                @if(($record->zatca_qr_position ?? 'footer') === 'footer')
                    <div class="mx-auto my-3 bg-gray-200 border border-gray-300 flex items-center justify-center"
                         style="width: {{ $footer['zatca_qr_size_px'] ?? 120 }}px; height: {{ $footer['zatca_qr_size_px'] ?? 120 }}px;">
                        <div class="text-center">
                            <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z" />
                            </svg>
                            <span class="text-xs text-gray-500">{{ __('ui.zatca_qr_label') }}</span>
                        </div>
                    </div>
                @endif

                <div class="text-sm text-black mt-2">{{ $footer['thank_you_en'] ?? 'Thank you!' }}</div>
                @if($record->show_bilingual)
                    <div class="text-sm text-black" dir="rtl">{{ $footer['thank_you_ar'] ?? 'شكراً لزيارتكم' }}</div>
                @endif

                @if(!empty($footer['custom_footer_text']))
                    <div class="text-xs text-gray-500 mt-2">{{ $footer['custom_footer_text'] }}</div>
                @endif
                @if($record->show_bilingual && !empty($footer['custom_footer_text_ar']))
                    <div class="text-xs text-gray-500" dir="rtl">{{ $footer['custom_footer_text_ar'] }}</div>
                @endif

                @if($footer['show_social_handles'] ?? false)
                    <div class="text-xs text-gray-500 mt-1">@WameedStore | fb.com/wameed</div>
                @endif
            </div>

            <div class="mt-4 border-t-2 border-dashed border-gray-300"></div>
        </div>
    </div>

    {{-- Configuration Summary --}}
    <details style="margin-top:20px; background:#fff; border-radius:12px; padding:16px 20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <summary style="font-weight:600; font-size:14px; cursor:pointer; color:#374151;">{{ __('ui.config_summary') }}</summary>
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-top:12px; font-size:13px;">
            <div>
                <h4 style="font-weight:600; font-size:13px; color:#6b7280; margin-bottom:8px;">{{ __('ui.header_design') }}</h4>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_logo_height') }}</span><span>{{ $header['logo_max_height_px'] ?? 60 }}px</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_name_font') }}</span><span>{{ $header['store_name_font_size'] ?? 'large' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_name_bold') }}</span><span>{{ ($header['store_name_bold'] ?? true) ? '✓' : '✗' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_separator') }}</span><span>{{ $header['separator'] ?? 'dashes' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_vat_number') }}</span><span>{{ ($header['show_vat_number'] ?? true) ? '✓' : '✗' }}</span></div>
            </div>
            <div>
                <h4 style="font-weight:600; font-size:13px; color:#6b7280; margin-bottom:8px;">{{ __('ui.body_design') }}</h4>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_item_font') }}</span><span>{{ $body['item_font_size'] ?? 'medium' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_price_align') }}</span><span>{{ $body['price_alignment'] ?? 'right' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.show_sku') }}</span><span>{{ ($body['show_sku'] ?? false) ? '✓' : '✗' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.totals_bold') }}</span><span>{{ ($body['totals_bold'] ?? true) ? '✓' : '✗' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_columns') }}</span><span>{{ $colName }}/{{ $colQty }}/{{ $colPrice }}%</span></div>
            </div>
            <div>
                <h4 style="font-weight:600; font-size:13px; color:#6b7280; margin-bottom:8px;">{{ __('ui.footer_design') }}</h4>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_qr_size') }}</span><span>{{ $footer['zatca_qr_size_px'] ?? 120 }}px</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_qr_position') }}</span><span>{{ $record->zatca_qr_position ?? 'footer' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_receipt_hash') }}</span><span>{{ ($footer['show_receipt_number'] ?? true) ? '✓' : '✗' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_cashier_label') }}</span><span>{{ ($footer['show_cashier_name'] ?? true) ? '✓' : '✗' }}</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:#9ca3af;">{{ __('ui.preview_socials_label') }}</span><span>{{ ($footer['show_social_handles'] ?? false) ? '✓' : '✗' }}</span></div>
            </div>
        </div>
    </details>
</div>
</body>
</html>
