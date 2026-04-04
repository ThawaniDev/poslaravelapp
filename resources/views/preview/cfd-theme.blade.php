<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $record->name }} — CFD Theme Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f3f4f6; margin: 0; padding: 24px 16px; }
        @media (prefers-color-scheme: dark) {
            body { background: #1f2937; }
            .info-bar { background: #374151 !important; color: #e5e7eb !important; }
            .info-bar .subtitle { color: #9ca3af !important; }
            .config-details { background: #374151 !important; color: #e5e7eb !important; }
        }
    </style>
</head>
<body>
@php
    $bgColor = $record->background_color ?? '#1a1a2e';
    $textColor = $record->text_color ?? '#e0e0e0';
    $accentColor = $record->accent_color ?? '#0f3460';
    $cartLayout = $record->cart_layout?->value ?? 'list';
    $idleLayout = $record->idle_layout?->value ?? 'slideshow';
    $animation = $record->animation_style?->value ?? 'fade';
    $thankYou = $record->thank_you_animation?->value ?? 'confetti';
    $transition = $record->transition_seconds ?? 5;
    $fontFamily = $record->font_family ?? 'system-ui';

    $sampleItems = [
        ['name' => 'Cappuccino', 'qty' => 2, 'price' => 1.800],
        ['name' => 'Croissant', 'qty' => 1, 'price' => 0.650],
        ['name' => 'Water 500ml', 'qty' => 3, 'price' => 0.200],
    ];
    $total = collect($sampleItems)->sum(fn($i) => $i['qty'] * $i['price']);
@endphp

<div style="max-width: 720px; margin: 0 auto;">
    {{-- Theme Info Bar --}}
    <div class="info-bar" style="background:#fff; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h3 style="font-size:18px; font-weight:600; margin:0;">{{ e($record->name) }}</h3>
                <p class="subtitle" style="font-size:14px; color:#6b7280; margin:4px 0 0;">{{ e($record->slug) }}</p>
            </div>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <span style="display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:500;
                    background:{{ match($idleLayout) { 'slideshow' => '#dbeafe', 'static_image' => '#fef9c3', 'video_loop' => '#dcfce7', default => '#f3f4f6' } }};
                    color:{{ match($idleLayout) { 'slideshow' => '#1e40af', 'static_image' => '#854d0e', 'video_loop' => '#166534', default => '#374151' } }};">
                    {{ $idleLayout }}
                </span>
                <span style="display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:500; background:{{ $record->is_active ? '#dcfce7' : '#fee2e2' }}; color:{{ $record->is_active ? '#166534' : '#991b1b' }};">
                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Color Palette --}}
    <div style="background:#fff; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <h4 style="font-weight:600; font-size:14px; margin:0 0 12px;">Colour Palette</h4>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            @foreach([
                ['label' => 'Background', 'color' => $bgColor],
                ['label' => 'Text', 'color' => $textColor],
                ['label' => 'Accent', 'color' => $accentColor],
            ] as $swatch)
                <div style="text-align:center;">
                    <div style="width:64px; height:64px; border-radius:8px; border:1px solid #d1d5db; box-shadow:0 1px 2px rgba(0,0,0,.05); background-color:{{ e($swatch['color']) }};"></div>
                    <p style="font-size:11px; color:#6b7280; margin:4px 0 0;">{{ $swatch['label'] }}</p>
                    <p style="font-size:11px; color:#9ca3af; font-family:monospace; margin:0;">{{ $swatch['color'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- CFD Screen Preview — Cart Mode --}}
    <div style="text-align:center; font-size:11px; color:#9ca3af; margin-bottom:6px;">Customer Facing Display — Cart Mode</div>
    <div style="border-radius:12px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); border:4px solid #1f2937; margin-bottom:24px;
                background-color:{{ e($bgColor) }}; color:{{ e($textColor) }}; font-family:{{ e($fontFamily) }}, system-ui, sans-serif;">

        {{-- Top bar --}}
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 20px; background-color:{{ e($accentColor) }};">
            @if($record->show_store_logo)
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:32px; height:32px; border-radius:4px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center;">
                        <svg style="width:20px; height:20px;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                    </div>
                    <span style="font-weight:700; font-size:14px;">Wameed Store</span>
                </div>
            @else
                <div></div>
            @endif
            @if($record->show_running_total)
                <div style="text-align:right;">
                    <div style="font-size:11px; opacity:.7;">Total</div>
                    <div style="font-size:20px; font-weight:700;">{{ number_format($total, 3) }} <span style="font-size:11px;">SAR</span></div>
                </div>
            @endif
        </div>

        {{-- Cart content --}}
        <div style="padding:20px; min-height:250px;">
            @if($cartLayout === 'grid')
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                    @foreach($sampleItems as $item)
                        <div style="border-radius:8px; padding:12px; text-align:center; background-color:{{ e($accentColor) }}33;">
                            <div style="width:40px; height:40px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; background-color:{{ e($accentColor) }};">
                                <span style="font-size:18px; font-weight:700;">{{ $item['qty'] }}</span>
                            </div>
                            <div style="font-size:14px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $item['name'] }}</div>
                            <div style="font-size:12px; opacity:.7; margin-top:4px;">{{ number_format($item['qty'] * $item['price'], 3) }} SAR</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach($sampleItems as $item)
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-radius:8px; background-color:{{ e($accentColor) }}1A;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span style="width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; background-color:{{ e($accentColor) }};">
                                    {{ $item['qty'] }}
                                </span>
                                <span style="font-weight:500;">{{ $item['name'] }}</span>
                            </div>
                            <span style="font-weight:600;">{{ number_format($item['qty'] * $item['price'], 3) }} SAR</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Totals --}}
            <div style="margin-top:24px; padding-top:16px; border-top:1px solid {{ e($accentColor) }}66;">
                <div style="display:flex; justify-content:space-between; font-size:14px; opacity:.7;">
                    <span>Subtotal</span><span>{{ number_format($total, 3) }} SAR</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:14px; opacity:.7; margin-top:4px;">
                    <span>VAT (5%)</span><span>{{ number_format($total * 0.05, 3) }} SAR</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:700; margin-top:8px; padding-top:8px; border-top:2px solid {{ e($accentColor) }};">
                    <span>Total</span><span>{{ number_format($total * 1.05, 3) }} SAR</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Idle Screen Preview --}}
    <div style="text-align:center; font-size:11px; color:#9ca3af; margin-bottom:6px;">Idle Screen — {{ e($idleLayout) }}</div>
    <div style="border-radius:12px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); border:4px solid #1f2937; margin-bottom:24px;
                background-color:{{ e($bgColor) }}; color:{{ e($textColor) }}; font-family:{{ e($fontFamily) }}, system-ui, sans-serif;">
        <div style="min-height:200px; display:flex; align-items:center; justify-content:center;">
            @if($idleLayout === 'slideshow')
                <div style="text-align:center;">
                    <div style="width:96px; height:96px; margin:0 auto 12px; border-radius:8px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center;">
                        <svg style="width:48px; height:48px; opacity:.3;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                        </svg>
                    </div>
                    <p style="font-size:14px; opacity:.5;">Slideshow</p>
                    <p style="font-size:12px; opacity:.3; margin-top:4px;">{{ $transition }}s transitions · {{ $animation }}</p>
                </div>
            @elseif($idleLayout === 'video_loop')
                <div style="text-align:center;">
                    <div style="width:96px; height:96px; margin:0 auto 12px; border-radius:8px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center;">
                        <svg style="width:48px; height:48px; opacity:.3;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                        </svg>
                    </div>
                    <p style="font-size:14px; opacity:.5;">Video Loop</p>
                </div>
            @else
                <div style="text-align:center;">
                    <div style="width:96px; height:96px; margin:0 auto 12px; border-radius:8px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center;">
                        <svg style="width:48px; height:48px; opacity:.3;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                        </svg>
                    </div>
                    <p style="font-size:14px; opacity:.5;">Static Image</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Thank You Animation Preview --}}
    <div style="background:#fff; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <h4 style="font-weight:600; font-size:14px; margin:0 0 12px;">Thank You Preview</h4>
        <div style="display:flex; justify-content:center;">
            <div style="width:100%; max-width:320px; border-radius:12px; overflow:hidden; border:2px solid #1f2937;
                        background-color:{{ e($bgColor) }}; color:{{ e($textColor) }}; font-family:{{ e($fontFamily) }}, system-ui, sans-serif;">
                <div style="padding:32px; text-align:center;">
                    @if($thankYou === 'confetti')
                        <div style="font-size:48px; margin-bottom:12px;">🎉</div>
                    @elseif($thankYou === 'check')
                        <div style="width:64px; height:64px; margin:0 auto 12px; border-radius:50%; display:flex; align-items:center; justify-content:center; background-color:{{ e($accentColor) }};">
                            <svg style="width:32px; height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    @endif
                    <h3 style="font-size:20px; font-weight:700; margin:0 0 4px;">Thank You!</h3>
                    <p style="font-size:14px; opacity:.6; margin:0;">Please come again</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Settings Summary --}}
    <details class="config-details" style="background:#fff; border-radius:12px; padding:16px 20px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <summary style="font-weight:600; font-size:14px; cursor:pointer; color:#374151;">Configuration Summary</summary>
        <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; margin-top:12px; font-size:13px;">
            <div><span style="color:#9ca3af;">Font Family:</span> {{ e($fontFamily) }}</div>
            <div><span style="color:#9ca3af;">Cart Layout:</span> {{ e($cartLayout) }}</div>
            <div><span style="color:#9ca3af;">Idle Layout:</span> {{ e($idleLayout) }}</div>
            <div><span style="color:#9ca3af;">Animation:</span> {{ e($animation) }}</div>
            <div><span style="color:#9ca3af;">Transition:</span> {{ $transition }}s</div>
            <div><span style="color:#9ca3af;">Thank You:</span> {{ e($thankYou) }}</div>
            <div><span style="color:#9ca3af;">Store Logo:</span> {{ $record->show_store_logo ? '✓' : '✗' }}</div>
            <div><span style="color:#9ca3af;">Running Total:</span> {{ $record->show_running_total ? '✓' : '✗' }}</div>
        </div>
    </details>
</div>
</body>
</html>
