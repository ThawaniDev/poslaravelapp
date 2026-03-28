<x-filament-panels::page>
    @php
        $record = $this->record;
        $barcode = $record->barcode_position ?? [];
        $rawFields = $record->field_layout ?? [];
        $labelW = $record->label_width_mm ?? 50;
        $labelH = $record->label_height_mm ?? 30;
        $scaleFactor = max(5, (int) ceil(300 / $labelW));
        $displayW = $labelW * $scaleFactor;
        $displayH = $labelH * $scaleFactor;
        $borderStyle = match($record->border_style?->value ?? 'solid') {
            'dashed' => 'dashed', 'dotted' => 'dotted', default => 'solid'
        };
        $bgColor = $record->background_color ?? '#FFFFFF';
        $fontFamily = $record->font_family ?? 'system-ui';

        // Determine if field_layout is simple format {"fields":[...]} or positioned format [{x,y,w,h,...}]
        $isSimpleFormat = false;
        $fields = [];
        if (is_array($rawFields) && isset($rawFields['fields']) && is_array($rawFields['fields'])) {
            $isSimpleFormat = true;
            $fields = $rawFields['fields'];
        } elseif (is_array($rawFields) && !empty($rawFields) && isset(reset($rawFields)['field_key'])) {
            $fields = array_values($rawFields);
        }

        // Font size name → mm height on physical label
        $fontSizeMap = [
            'xs' => 1.5,
            'small' => 2,
            'medium' => 2.8,
            'large' => 3.8,
            'extra-large' => 5,
        ];

        $sampleData = [
            'product_name' => 'Arabica Coffee Beans',
            'product_name_ar' => 'حبوب قهوة أرابيكا',
            'sku' => 'COF-001',
            'barcode' => '6291041500213',
            'price' => '4.500 SAR',
            'weight' => '250g',
            'unit' => 'per kg',
            'expiry_date' => '2025-12-31',
            'manufacture_date' => '2025-01-15',
            'karat' => '21K',
            'drug_schedule' => 'OTC',
            'origin_country' => 'Colombia',
            'batch_number' => 'B-20250115',
            'store_name' => 'Thawani Store',
            'custom_text' => 'XL',
            'making_charge' => '15.000 SAR',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Template Info Bar --}}
        <x-filament::section>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $record->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->name_ar }}</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <x-filament::badge :color="match($record->label_type?->value) { 'barcode' => 'gray', 'price' => 'success', 'shelf' => 'info', 'jewelry' => 'warning', 'pharmacy' => 'danger', default => 'gray' }">
                        {{ $record->label_type?->value ?? 'unknown' }}
                    </x-filament::badge>
                    <x-filament::badge color="primary">{{ $labelW }}×{{ $labelH }}mm</x-filament::badge>
                    @if($record->show_border)
                        <x-filament::badge color="gray">{{ __('ui.bordered') }} ({{ $borderStyle }})</x-filament::badge>
                    @endif
                    @if($record->barcode_type)
                        <x-filament::badge color="gray">{{ $record->barcode_type->value }}</x-filament::badge>
                    @endif
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        {{-- Label Preview --}}
        <div class="flex justify-center">
            <div class="relative bg-gray-50 dark:bg-gray-800/50 p-8 rounded-xl">
                {{-- Scale reference --}}
                <div class="text-xs text-gray-400 text-center mb-3">
                    {{ __('ui.physical_size') }}: {{ $labelW }}mm × {{ $labelH }}mm &mdash; {{ $scaleFactor }}× zoom
                </div>

                {{-- The label --}}
                <div class="relative mx-auto"
                     style="width: {{ $displayW }}px; height: {{ $displayH }}px;
                            background-color: {{ e($bgColor) }};
                            font-family: {{ e($fontFamily) }}, system-ui, sans-serif;
                            color: #1a1a1a;
                            @if($record->show_border) border: 2px {{ $borderStyle }} #333; @else border: 1px solid #d1d5db; @endif
                            box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.15), 0 0 0 1px rgb(0 0 0 / 0.05);">

                    @if($isSimpleFormat)
                        {{-- Simple fields layout: evenly stacked --}}
                        <div class="flex flex-col justify-evenly h-full px-3 py-2">
                            @foreach($fields as $fieldName)
                                <div class="text-center truncate" style="font-size: {{ round(($fontSizeMap['medium'] ?? 2.8) * $scaleFactor, 1) }}px; line-height: 1.3;">
                                    {{ $sampleData[$fieldName] ?? $fieldName }}
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Positioned fields --}}
                        @foreach($fields as $field)
                            @php
                                $fieldKey = $field['field_key'] ?? '';
                                $value = $sampleData[$fieldKey] ?? $fieldKey;
                                $fSizeName = $field['font_size'] ?? 'medium';
                                $fSizePx = round(($fontSizeMap[$fSizeName] ?? 2.8) * $scaleFactor, 1);
                                $isBold = $field['is_bold'] ?? false;
                                $textAlign = $field['alignment'] ?? 'left';
                                $justifyContent = match($textAlign) {
                                    'center' => 'center', 'right' => 'flex-end', default => 'flex-start'
                                };
                            @endphp
                            <div class="absolute overflow-hidden"
                                 style="left: {{ $field['x'] ?? 0 }}%;
                                        top: {{ $field['y'] ?? 0 }}%;
                                        width: {{ $field['w'] ?? 50 }}%;
                                        height: {{ $field['h'] ?? 20 }}%;
                                        font-size: {{ $fSizePx }}px;
                                        line-height: 1.2;
                                        text-align: {{ $textAlign }};
                                        display: flex;
                                        align-items: center;
                                        justify-content: {{ $justifyContent }};
                                        {{ $isBold ? 'font-weight: 700;' : 'font-weight: 400;' }}
                                        padding: 1px 2px;">
                                <span class="truncate block w-full" style="text-align: {{ $textAlign }};">{{ $value }}</span>
                            </div>
                        @endforeach

                        {{-- Barcode area --}}
                        @if(!empty($barcode))
                            <div class="absolute flex flex-col items-center justify-center"
                                 style="left: {{ $barcode['x'] ?? 0 }}%;
                                        top: {{ $barcode['y'] ?? 0 }}%;
                                        width: {{ $barcode['w'] ?? 100 }}%;
                                        height: {{ $barcode['h'] ?? 30 }}%;
                                        background: rgba(255,255,255,0.92);">
                                @php $barcodeBarH = max(14, (int)($displayH * ($barcode['h'] ?? 30) / 160)); @endphp
                                <div class="flex items-end gap-px" style="height: {{ $barcodeBarH }}px;">
                                    @for($i = 0; $i < 40; $i++)
                                        <div class="bg-black" style="width: {{ rand(1, 3) }}px; height: {{ rand(55, 100) }}%;"></div>
                                    @endfor
                                </div>
                                @if($record->show_barcode_number)
                                    <div class="text-center mt-0.5" style="font-size: {{ round(($fontSizeMap['xs'] ?? 1.5) * $scaleFactor, 1) }}px;">
                                        {{ $sampleData['barcode'] }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Empty state --}}
                    @if(empty($fields) && empty($barcode))
                        <div class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">
                            {{ __('ui.no_fields_configured') }}
                        </div>
                    @endif
                </div>

                {{-- Ruler marks --}}
                <div class="flex justify-between text-[10px] text-gray-400 mt-2" style="width: {{ $displayW }}px; margin-left: auto; margin-right: auto;">
                    <span>0</span>
                    <span>{{ round($labelW / 4) }}mm</span>
                    <span>{{ round($labelW / 2) }}mm</span>
                    <span>{{ round($labelW * 3 / 4) }}mm</span>
                    <span>{{ $labelW }}mm</span>
                </div>
            </div>
        </div>

        {{-- Field Layout Details --}}
        @if(!$isSimpleFormat && !empty($fields))
            <x-filament::section heading="{{ __('ui.field_layout_details') }}" collapsible collapsed>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b dark:border-gray-700">
                                <th class="py-2 px-3 text-gray-500">#</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.field') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.label_english') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.label_arabic') }}</th>
                                <th class="py-2 px-3 text-gray-500">Position (x, y)</th>
                                <th class="py-2 px-3 text-gray-500">Size (w × h)</th>
                                <th class="py-2 px-3 text-gray-500">Font Size</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.alignment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fields as $idx => $field)
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2 px-3 text-gray-400">{{ $idx + 1 }}</td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $field['field_key'] ?? '-' }}</td>
                                    <td class="py-2 px-3">{{ $field['label_en'] ?? '-' }}</td>
                                    <td class="py-2 px-3" dir="rtl">{{ $field['label_ar'] ?? '-' }}</td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $field['x'] ?? 0 }}%, {{ $field['y'] ?? 0 }}%</td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $field['w'] ?? 0 }}% × {{ $field['h'] ?? 0 }}%</td>
                                    <td class="py-2 px-3">
                                        {{ $field['font_size'] ?? 'medium' }}
                                        @if($field['is_bold'] ?? false) <span class="font-bold">(Bold)</span> @endif
                                    </td>
                                    <td class="py-2 px-3">{{ $field['alignment'] ?? 'left' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($barcode))
                    <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold text-sm mb-2">{{ __('ui.barcode_settings') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            <div><span class="text-gray-500">{{ __('ui.barcode_type') }}:</span> {{ $record->barcode_type?->value ?? 'N/A' }}</div>
                            <div><span class="text-gray-500">Position:</span> {{ $barcode['x'] ?? 0 }}%, {{ $barcode['y'] ?? 0 }}%</div>
                            <div><span class="text-gray-500">Size:</span> {{ $barcode['w'] ?? 100 }}% × {{ $barcode['h'] ?? 30 }}%</div>
                            <div><span class="text-gray-500">{{ __('ui.show_number') }}:</span> {{ $record->show_barcode_number ? '✓' : '✗' }}</div>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- Simple format info --}}
        @if($isSimpleFormat)
            <x-filament::section heading="{{ __('ui.field_layout_details') }}" collapsible collapsed>
                <p class="text-sm text-gray-500 mb-3">{{ __('ui.simple_label_layout_note') }}</p>
                <div class="flex gap-2 flex-wrap">
                    @foreach($fields as $fieldName)
                        <x-filament::badge color="gray" size="lg">{{ $fieldName }}</x-filament::badge>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
