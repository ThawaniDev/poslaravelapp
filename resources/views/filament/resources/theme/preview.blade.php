<x-filament-panels::page>
    @php
        $record = $this->record;
        $primary = $record->primary_color ?? '#3b82f6';
        $secondary = $record->secondary_color ?? '#6366f1';
        $background = $record->background_color ?? '#ffffff';
        $text = $record->text_color ?? '#1f2937';
        $typography = $record->typography_config ?? [];
        $spacing = $record->spacing_config ?? [];
        $border = $record->border_config ?? [];
        $shadow = $record->shadow_config ?? [];
        $animation = $record->animation_config ?? [];
        $cssVars = $record->css_variables ?? [];
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
                    @if($record->is_system)
                        <x-filament::badge color="info">{{ __('ui.system') }}</x-filament::badge>
                    @endif
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        {{-- Color Palette --}}
        <x-filament::section heading="{{ __('ui.colour_palette') }}">
            <div class="flex gap-6 flex-wrap">
                @foreach([
                    ['label' => __('ui.primary_color'), 'color' => $primary],
                    ['label' => __('ui.secondary_color'), 'color' => $secondary],
                    ['label' => __('ui.background_color'), 'color' => $background],
                    ['label' => __('ui.text_color'), 'color' => $text],
                ] as $swatch)
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-xl border-2 border-gray-200 dark:border-gray-600 shadow-md"
                             style="background-color: {{ e($swatch['color']) }};"></div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $swatch['label'] }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $swatch['color'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- UI Component Preview --}}
        <x-filament::section heading="{{ __('ui.component_preview') }}">
            <div class="rounded-xl p-6" style="background-color: {{ e($background) }}; color: {{ e($text) }};">
                {{-- Typography --}}
                <div class="mb-6">
                    <h2 class="text-2xl font-bold mb-1" style="color: {{ e($text) }};">Heading Example</h2>
                    <h3 class="text-lg font-semibold mb-1" style="color: {{ e($text) }};">Subheading Example</h3>
                    <p class="text-sm" style="color: {{ e($text) }}; opacity: 0.7;">This is body text that demonstrates how the theme looks with regular content. The quick brown fox jumps over the lazy dog.</p>
                </div>

                {{-- Buttons --}}
                <div class="flex gap-3 flex-wrap mb-6">
                    <button class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm"
                            style="background-color: {{ e($primary) }};">
                        Primary Button
                    </button>
                    <button class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm"
                            style="background-color: {{ e($secondary) }};">
                        Secondary Button
                    </button>
                    <button class="px-5 py-2.5 rounded-lg text-sm font-semibold border-2 shadow-sm"
                            style="border-color: {{ e($primary) }}; color: {{ e($primary) }};">
                        Outline Button
                    </button>
                </div>

                {{-- Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @foreach(['Product Card', 'Category Card', 'Summary Card'] as $card)
                        <div class="rounded-lg p-4 border shadow-sm" style="border-color: {{ e($primary) }}22; background-color: {{ e($background) }};">
                            <div class="w-full h-16 rounded mb-3" style="background-color: {{ e($primary) }}15;"></div>
                            <h4 class="font-semibold text-sm mb-1" style="color: {{ e($text) }};">{{ $card }}</h4>
                            <p class="text-xs" style="color: {{ e($text) }}; opacity: 0.6;">Sample description text</p>
                            <div class="mt-3 text-sm font-bold" style="color: {{ e($primary) }};">4.500 SAR</div>
                        </div>
                    @endforeach
                </div>

                {{-- Badges & Chips --}}
                <div class="flex gap-2 flex-wrap mb-6">
                    <span class="px-3 py-1 rounded-full text-xs font-medium text-white" style="background-color: {{ e($primary) }};">Active</span>
                    <span class="px-3 py-1 rounded-full text-xs font-medium text-white" style="background-color: {{ e($secondary) }};">New</span>
                    <span class="px-3 py-1 rounded-full text-xs font-medium border" style="border-color: {{ e($primary) }}; color: {{ e($primary) }};">Featured</span>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" style="background-color: {{ e($primary) }}22; color: {{ e($primary) }};">Sale</span>
                </div>

                {{-- Navigation bar sample --}}
                <div class="rounded-lg p-3 flex items-center justify-between" style="background-color: {{ e($primary) }};">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white text-sm font-bold">T</div>
                        <span class="text-white font-semibold text-sm">Thawani POS</span>
                    </div>
                    <div class="flex gap-4 text-white/80 text-xs">
                        <span class="text-white font-medium">Products</span>
                        <span>Categories</span>
                        <span>Orders</span>
                        <span>Settings</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Contrast Preview --}}
        <x-filament::section heading="{{ __('ui.contrast_preview') }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Primary on Background --}}
                <div class="rounded-lg p-4 text-center" style="background-color: {{ e($background) }};">
                    <div class="text-lg font-bold mb-1" style="color: {{ e($primary) }};">Primary on Background</div>
                    <div class="text-sm" style="color: {{ e($text) }};">Regular text on background</div>
                </div>
                {{-- Text on Primary --}}
                <div class="rounded-lg p-4 text-center text-white" style="background-color: {{ e($primary) }};">
                    <div class="text-lg font-bold mb-1">White on Primary</div>
                    <div class="text-sm opacity-80">Secondary content</div>
                </div>
                {{-- Secondary on Background --}}
                <div class="rounded-lg p-4 text-center" style="background-color: {{ e($background) }};">
                    <div class="text-lg font-bold mb-1" style="color: {{ e($secondary) }};">Secondary on Background</div>
                    <div class="text-sm" style="color: {{ e($text) }};">Regular text on background</div>
                </div>
                {{-- Text on Secondary --}}
                <div class="rounded-lg p-4 text-center text-white" style="background-color: {{ e($secondary) }};">
                    <div class="text-lg font-bold mb-1">White on Secondary</div>
                    <div class="text-sm opacity-80">Secondary content</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Config Details --}}
        @if(!empty($typography) || !empty($spacing) || !empty($border) || !empty($shadow) || !empty($cssVars))
            <x-filament::section heading="{{ __('ui.configuration_summary') }}" collapsible collapsed>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if(!empty($typography))
                        <div>
                            <h4 class="font-semibold text-sm mb-2">Typography</h4>
                            <dl class="space-y-1 text-sm">
                                @foreach($typography as $key => $val)
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ $key }}</dt><dd class="font-mono text-xs">{{ is_array($val) ? json_encode($val) : $val }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                    @if(!empty($spacing))
                        <div>
                            <h4 class="font-semibold text-sm mb-2">Spacing</h4>
                            <dl class="space-y-1 text-sm">
                                @foreach($spacing as $key => $val)
                                    <div class="flex justify-between"><dt class="text-gray-500">{{ $key }}</dt><dd class="font-mono text-xs">{{ is_array($val) ? json_encode($val) : $val }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                    @if(!empty($cssVars))
                        <div class="md:col-span-2">
                            <h4 class="font-semibold text-sm mb-2">CSS Variables</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                                @foreach($cssVars as $key => $val)
                                    <div>
                                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $key }}</code>
                                        <span class="text-gray-500 ml-1">{{ is_array($val) ? json_encode($val) : $val }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
