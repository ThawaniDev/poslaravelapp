<x-filament-panels::page>
    @php
        $record = $this->record;
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

    <div class="space-y-6">
        {{-- Theme Info Bar --}}
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $record->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->slug }}</p>
                </div>
                <div class="flex gap-2">
                    <x-filament::badge :color="match($idleLayout) { 'slideshow' => 'info', 'static_image' => 'warning', 'video_loop' => 'success', default => 'gray' }">
                        {{ $idleLayout }}
                    </x-filament::badge>
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        {{-- Color Palette --}}
        <x-filament::section heading="{{ __('ui.colour_palette') }}">
            <div class="flex gap-4 flex-wrap">
                @foreach([
                    ['label' => __('ui.background_color'), 'color' => $bgColor],
                    ['label' => __('ui.text_color'), 'color' => $textColor],
                    ['label' => __('ui.accent_color'), 'color' => $accentColor],
                ] as $swatch)
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm"
                             style="background-color: {{ e($swatch['color']) }};"></div>
                        <p class="text-xs text-gray-500 mt-1">{{ $swatch['label'] }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $swatch['color'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- CFD Screen Preview --}}
        <div class="flex justify-center">
            <div class="w-full max-w-2xl">
                <div class="text-xs text-gray-400 text-center mb-2">{{ __('ui.customer_facing_display') }} — {{ __('ui.cart_mode') }}</div>

                {{-- Main display --}}
                <div class="rounded-xl overflow-hidden shadow-2xl border-4 border-gray-800"
                     style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }}; font-family: {{ e($fontFamily) }}, system-ui, sans-serif;">

                    {{-- Top bar --}}
                    <div class="flex items-center justify-between px-5 py-3"
                         style="background-color: {{ e($accentColor) }};">
                        @if($record->show_store_logo)
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded bg-white/20 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </div>
                                <span class="font-bold text-sm">Thawani Store</span>
                            </div>
                        @else
                            <div></div>
                        @endif
                        @if($record->show_running_total)
                            <div class="text-right">
                                <div class="text-xs opacity-70">Total</div>
                                <div class="text-xl font-bold">{{ number_format($total, 3) }} <span class="text-xs">SAR</span></div>
                            </div>
                        @endif
                    </div>

                    {{-- Cart content --}}
                    <div class="p-5 min-h-[250px]">
                        @if($cartLayout === 'grid')
                            {{-- Grid layout --}}
                            <div class="grid grid-cols-3 gap-3">
                                @foreach($sampleItems as $item)
                                    <div class="rounded-lg p-3 text-center"
                                         style="background-color: {{ e($accentColor) }}33;">
                                        <div class="w-10 h-10 rounded-full mx-auto mb-2 flex items-center justify-center"
                                             style="background-color: {{ e($accentColor) }};">
                                            <span class="text-lg font-bold">{{ $item['qty'] }}</span>
                                        </div>
                                        <div class="text-sm font-semibold truncate">{{ $item['name'] }}</div>
                                        <div class="text-xs opacity-70 mt-1">{{ number_format($item['qty'] * $item['price'], 3) }} SAR</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- List layout --}}
                            <div class="space-y-2">
                                @foreach($sampleItems as $index => $item)
                                    <div class="flex items-center justify-between py-3 px-4 rounded-lg"
                                         style="background-color: {{ e($accentColor) }}1A;">
                                        <div class="flex items-center gap-3">
                                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold"
                                                  style="background-color: {{ e($accentColor) }};">
                                                {{ $item['qty'] }}
                                            </span>
                                            <span class="font-medium">{{ $item['name'] }}</span>
                                        </div>
                                        <span class="font-semibold">{{ number_format($item['qty'] * $item['price'], 3) }} SAR</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Totals area --}}
                        <div class="mt-6 pt-4" style="border-top: 1px solid {{ e($accentColor) }}66;">
                            <div class="flex justify-between text-sm opacity-70">
                                <span>Subtotal</span>
                                <span>{{ number_format($total, 3) }} SAR</span>
                            </div>
                            <div class="flex justify-between text-sm opacity-70 mt-1">
                                <span>VAT (5%)</span>
                                <span>{{ number_format($total * 0.05, 3) }} SAR</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold mt-2 pt-2" style="border-top: 2px solid {{ e($accentColor) }};">
                                <span>Total</span>
                                <span>{{ number_format($total * 1.05, 3) }} SAR</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Idle Screen Preview --}}
                <div class="text-xs text-gray-400 text-center mt-6 mb-2">{{ __('ui.idle_screen') }} — {{ $idleLayout }}</div>
                <div class="rounded-xl overflow-hidden shadow-2xl border-4 border-gray-800"
                     style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }}; font-family: {{ e($fontFamily) }}, system-ui, sans-serif;">
                    <div class="min-h-[200px] flex items-center justify-center">
                        @if($idleLayout === 'slideshow')
                            <div class="text-center">
                                <div class="w-24 h-24 mx-auto rounded-lg bg-white/10 flex items-center justify-center mb-3">
                                    <svg class="w-12 h-12 opacity-30" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                                    </svg>
                                </div>
                                <p class="text-sm opacity-50">Slideshow</p>
                                <p class="text-xs opacity-30 mt-1">{{ $transition }}s transitions · {{ $animation }}</p>
                            </div>
                        @elseif($idleLayout === 'video_loop')
                            <div class="text-center">
                                <div class="w-24 h-24 mx-auto rounded-lg bg-white/10 flex items-center justify-center mb-3">
                                    <svg class="w-12 h-12 opacity-30" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                                    </svg>
                                </div>
                                <p class="text-sm opacity-50">Video Loop</p>
                            </div>
                        @else
                            <div class="text-center">
                                <div class="w-24 h-24 mx-auto rounded-lg bg-white/10 flex items-center justify-center mb-3">
                                    <svg class="w-12 h-12 opacity-30" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                                    </svg>
                                </div>
                                <p class="text-sm opacity-50">Static Image</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Thank You Animation Preview --}}
        <x-filament::section heading="{{ __('ui.thank_you_preview') }}">
            <div class="flex justify-center">
                <div class="w-full max-w-sm rounded-xl overflow-hidden shadow-lg border-2 border-gray-800"
                     style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }}; font-family: {{ e($fontFamily) }}, system-ui, sans-serif;">
                    <div class="p-8 text-center">
                        @if($thankYou === 'confetti')
                            <div class="text-4xl mb-3">🎉</div>
                        @elseif($thankYou === 'check')
                            <div class="w-16 h-16 mx-auto mb-3 rounded-full flex items-center justify-center" style="background-color: {{ e($accentColor) }};">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        @endif
                        <h3 class="text-xl font-bold mb-1">Thank You!</h3>
                        <p class="text-sm opacity-60">Please come again</p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Settings Summary --}}
        <x-filament::section heading="{{ __('ui.configuration_summary') }}" collapsible collapsed>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-500">{{ __('ui.font_family') }}:</span> {{ $fontFamily }}</div>
                <div><span class="text-gray-500">{{ __('ui.cart_layout') }}:</span> {{ $cartLayout }}</div>
                <div><span class="text-gray-500">{{ __('ui.idle_layout') }}:</span> {{ $idleLayout }}</div>
                <div><span class="text-gray-500">{{ __('ui.animation_style') }}:</span> {{ $animation }}</div>
                <div><span class="text-gray-500">{{ __('ui.transition_seconds') }}:</span> {{ $transition }}s</div>
                <div><span class="text-gray-500">{{ __('ui.thank_you_animation') }}:</span> {{ $thankYou }}</div>
                <div><span class="text-gray-500">{{ __('ui.show_store_logo') }}:</span> {{ $record->show_store_logo ? '✓' : '✗' }}</div>
                <div><span class="text-gray-500">{{ __('ui.show_running_total') }}:</span> {{ $record->show_running_total ? '✓' : '✗' }}</div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
