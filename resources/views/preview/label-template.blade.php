<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Preview – {{ $record->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4">
@php
    $barcode = $record->barcode_position ?? [];
    $rawFields = $record->field_layout ?? [];
    $labelW = $record->label_width_mm ?? 50;
    $labelH = $record->label_height_mm ?? 30;
    $scaleFactor = max(5, (int) ceil(300 / $labelW));
    $displayW = $labelW * $scaleFactor;
    $displayH = $labelH * $scaleFactor;
    $borderStyleVal = match($record->border_style ?? 'solid') {
        'dashed' => 'dashed', 'dotted' => 'dotted', default => 'solid'
    };
    $bgColor = $record->background_color ?? '#FFFFFF';
    $fontFamily = $record->font_family ?? 'system-ui';

    $isSimpleFormat = false;
    $fields = [];
    if (is_array($rawFields) && isset($rawFields['fields']) && is_array($rawFields['fields'])) {
        $isSimpleFormat = true;
        $fields = $rawFields['fields'];
    } elseif (is_array($rawFields) && !empty($rawFields) && isset(reset($rawFields)['field_key'])) {
        $fields = array_values($rawFields);
    }

    $fontSizeMap = [
        'xs' => 1.5, 'small' => 2, 'medium' => 2.8, 'large' => 3.8, 'extra-large' => 5,
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
        'store_name' => 'Wameed Store',
        'custom_text' => 'XL',
        'making_charge' => '15.000 SAR',
    ];
@endphp

<div class="max-w-3xl mx-auto space-y-6">
    {{-- Template Info --}}
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $record->name }}</h1>
                <p class="text-sm text-gray-500">{{ $record->name_ar }}</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $record->label_type ?? 'unknown' }}
                </span>
                <span class="px-2 py-1 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                    {{ $labelW }}×{{ $labelH }}mm
                </span>
                @if($record->show_border)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">
                        Bordered ({{ $borderStyleVal }})
                    </span>
                @endif
                @if($record->barcode_type)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">
                        {{ $record->barcode_type }}
                    </span>
                @endif
                <span class="px-2 py-1 rounded text-xs font-medium {{ $record->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Label Preview --}}
    <div class="flex justify-center">
        <div class="bg-white rounded-xl shadow p-8">
            <div class="text-xs text-gray-400 text-center mb-3">
                Physical size: {{ $labelW }}mm × {{ $labelH }}mm — {{ $scaleFactor }}× zoom
            </div>

            <div class="relative mx-auto"
                 style="width: {{ $displayW }}px; height: {{ $displayH }}px;
                        background-color: {{ e($bgColor) }};
                        font-family: {{ e($fontFamily) }}, system-ui, sans-serif;
                        color: #1a1a1a;
                        @if($record->show_border) border: 2px {{ $borderStyleVal }} #333; @else border: 1px solid #d1d5db; @endif
                        box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.15), 0 0 0 1px rgb(0 0 0 / 0.05);">

                @if($isSimpleFormat)
                    <div class="flex flex-col justify-evenly h-full px-3 py-2">
                        @foreach($fields as $fieldName)
                            <div class="text-center truncate" style="font-size: {{ round(($fontSizeMap['medium'] ?? 2.8) * $scaleFactor, 1) }}px; line-height: 1.3;">
                                {{ $sampleData[$fieldName] ?? $fieldName }}
                            </div>
                        @endforeach
                    </div>
                @else
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

                @if(empty($fields) && empty($barcode))
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">
                        No fields configured
                    </div>
                @endif
            </div>

            {{-- Ruler --}}
            <div class="flex justify-between text-[10px] text-gray-400 mt-2" style="width: {{ $displayW }}px; margin-left: auto; margin-right: auto;">
                <span>0</span>
                <span>{{ round($labelW / 4) }}mm</span>
                <span>{{ round($labelW / 2) }}mm</span>
                <span>{{ round($labelW * 3 / 4) }}mm</span>
                <span>{{ $labelW }}mm</span>
            </div>
        </div>
    </div>

    {{-- Field Details --}}
    @if(!$isSimpleFormat && !empty($fields))
        <details class="bg-white rounded-xl shadow p-6">
            <summary class="font-semibold text-gray-900 cursor-pointer">Field Layout Details</summary>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2 px-3 text-gray-500">#</th>
                            <th class="py-2 px-3 text-gray-500">Field</th>
                            <th class="py-2 px-3 text-gray-500">Label (EN)</th>
                            <th class="py-2 px-3 text-gray-500">Label (AR)</th>
                            <th class="py-2 px-3 text-gray-500">Position</th>
                            <th class="py-2 px-3 text-gray-500">Size</th>
                            <th class="py-2 px-3 text-gray-500">Font</th>
                            <th class="py-2 px-3 text-gray-500">Align</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fields as $idx => $field)
                            <tr class="border-b hover:bg-gray-50">
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
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-sm mb-2">Barcode Settings</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div><span class="text-gray-500">Type:</span> {{ $record->barcode_type ?? 'N/A' }}</div>
                        <div><span class="text-gray-500">Position:</span> {{ $barcode['x'] ?? 0 }}%, {{ $barcode['y'] ?? 0 }}%</div>
                        <div><span class="text-gray-500">Size:</span> {{ $barcode['w'] ?? 100 }}% × {{ $barcode['h'] ?? 30 }}%</div>
                        <div><span class="text-gray-500">Show Number:</span> {{ $record->show_barcode_number ? '✓' : '✗' }}</div>
                    </div>
                </div>
            @endif
        </details>
    @endif

    @if($isSimpleFormat)
        <details class="bg-white rounded-xl shadow p-6">
            <summary class="font-semibold text-gray-900 cursor-pointer">Field Layout Details</summary>
            <p class="mt-3 text-sm text-gray-500">Simple layout — fields are stacked vertically in order.</p>
            <div class="flex gap-2 flex-wrap mt-3">
                @foreach($fields as $fieldName)
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ $fieldName }}</span>
                @endforeach
            </div>
        </details>
    @endif
</div>
</body>
</html>
