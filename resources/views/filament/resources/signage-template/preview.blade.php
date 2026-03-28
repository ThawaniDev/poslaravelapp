<x-filament-panels::page>
    @php
        $record = $this->record;
        $bgColor = $record->background_color ?? '#FFFFFF';
        $textColor = $record->text_color ?? '#333333';
        $fontFamily = $record->font_family ?? 'system-ui';
        $transition = $record->transition_style ?? 'fade';
        $regions = $record->layout_config ?? [];
        $placeholders = $record->placeholder_content ?? [];
        $templateType = $record->template_type?->value ?? 'info_board';

        $regionColors = [
            'image' => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'icon' => '🖼'],
            'text' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'icon' => '📝'],
            'product_grid' => ['bg' => '#d1fae5', 'border' => '#10b981', 'icon' => '🛍'],
            'video' => ['bg' => '#ede9fe', 'border' => '#8b5cf6', 'icon' => '🎬'],
            'clock' => ['bg' => '#fce7f3', 'border' => '#ec4899', 'icon' => '🕐'],
        ];
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
                    <x-filament::badge :color="match($templateType) { 'menu_board' => 'success', 'promo_slideshow' => 'warning', 'queue_display' => 'info', 'info_board' => 'primary', default => 'gray' }">
                        {{ str_replace('_', ' ', ucfirst($templateType)) }}
                    </x-filament::badge>
                    <x-filament::badge color="gray">{{ $transition }}</x-filament::badge>
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        {{-- Color Palette --}}
        <x-filament::section heading="{{ __('ui.styling') }}">
            <div class="flex gap-4 flex-wrap items-center">
                @foreach([
                    ['label' => __('ui.background_color'), 'color' => $bgColor],
                    ['label' => __('ui.text_color'), 'color' => $textColor],
                ] as $swatch)
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm"
                             style="background-color: {{ e($swatch['color']) }};"></div>
                        <p class="text-xs text-gray-500 mt-1">{{ $swatch['label'] }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $swatch['color'] }}</p>
                    </div>
                @endforeach
                <div class="text-sm text-gray-500 ml-4">
                    <span class="font-medium">{{ __('ui.font_family') }}:</span> {{ $fontFamily }}
                </div>
            </div>
        </x-filament::section>

        {{-- Signage Screen Preview --}}
        <div class="flex justify-center">
            <div class="w-full max-w-3xl">
                <div class="text-xs text-gray-400 text-center mb-2">{{ __('ui.signage_display_preview') }} (16:9)</div>

                {{-- Screen frame --}}
                <div class="rounded-xl overflow-hidden shadow-2xl border-4 border-gray-800 relative"
                     style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }}; font-family: {{ e($fontFamily) }}, system-ui, sans-serif; aspect-ratio: 16/9;">

                    @if(!empty($regions))
                        @foreach($regions as $region)
                            @php
                                $type = $region['type'] ?? 'text';
                                $pos = $region['position'] ?? [];
                                $x = $pos['x'] ?? ($region['x'] ?? 0);
                                $y = $pos['y'] ?? ($region['y'] ?? 0);
                                $w = $pos['w'] ?? ($region['w'] ?? 100);
                                $h = $pos['h'] ?? ($region['h'] ?? 100);
                                $regionId = $region['region_id'] ?? 'unknown';
                                $colors = $regionColors[$type] ?? ['bg' => '#f3f4f6', 'border' => '#9ca3af', 'icon' => '❓'];
                                $content = $placeholders[$regionId] ?? ($region['default_content'] ?? '');
                            @endphp
                            <div class="absolute overflow-hidden flex flex-col"
                                 style="left: {{ $x }}%; top: {{ $y }}%; width: {{ $w }}%; height: {{ $h }}%;
                                        background-color: {{ $colors['bg'] }}33;
                                        border: 2px dashed {{ $colors['border'] }};
                                        padding: 8px;">
                                {{-- Region header --}}
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="text-sm">{{ $colors['icon'] }}</span>
                                    <span class="text-xs font-bold uppercase tracking-wide" style="color: {{ $colors['border'] }};">
                                        {{ $regionId }}
                                    </span>
                                    <span class="text-xs opacity-50">({{ $type }})</span>
                                </div>

                                {{-- Region content --}}
                                <div class="flex-1 flex items-center justify-center rounded"
                                     style="background-color: {{ $colors['bg'] }}44;">
                                    @if($type === 'clock')
                                        <div class="text-center">
                                            <div class="text-2xl font-bold" style="color: {{ $colors['border'] }};">{{ now()->format('H:i') }}</div>
                                            <div class="text-xs opacity-60">{{ now()->format('D, M d') }}</div>
                                        </div>
                                    @elseif($type === 'product_grid')
                                        <div class="grid grid-cols-3 gap-1 w-full p-1">
                                            @for($i = 0; $i < 6; $i++)
                                                <div class="aspect-square rounded bg-white/50 flex items-center justify-center">
                                                    <div class="w-4 h-4 rounded bg-gray-300"></div>
                                                </div>
                                            @endfor
                                        </div>
                                    @elseif($type === 'video')
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto opacity-40" style="color: {{ $colors['border'] }};" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                                            </svg>
                                            <div class="text-xs opacity-40 mt-1">Video</div>
                                        </div>
                                    @elseif($type === 'image')
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto opacity-40" style="color: {{ $colors['border'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5"/>
                                            </svg>
                                            @if($content)
                                                <div class="text-xs opacity-60 mt-1 truncate px-1">{{ $content }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-sm text-center px-2 overflow-hidden" style="color: {{ e($textColor) }};">
                                            {{ $content ?: 'Sample text content' }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center opacity-40">
                                <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/>
                                </svg>
                                <p>{{ __('ui.no_regions_configured') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Region Details --}}
        @if(!empty($regions))
            <x-filament::section heading="{{ __('ui.region_details') }}" collapsible collapsed>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b dark:border-gray-700">
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.region_id') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.region_type') }}</th>
                                <th class="py-2 px-3 text-gray-500">Position (x, y)</th>
                                <th class="py-2 px-3 text-gray-500">Size (w × h)</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.default_content') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($regions as $region)
                                @php
                                    $pos = $region['position'] ?? [];
                                    $rx = $pos['x'] ?? ($region['x'] ?? 0);
                                    $ry = $pos['y'] ?? ($region['y'] ?? 0);
                                    $rw = $pos['w'] ?? ($region['w'] ?? 100);
                                    $rh = $pos['h'] ?? ($region['h'] ?? 100);
                                @endphp
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-3 font-mono text-xs font-bold">{{ $region['region_id'] ?? '-' }}</td>
                                    <td class="py-2 px-3">
                                        <span class="inline-flex items-center gap-1">
                                            {{ $regionColors[$region['type'] ?? 'text']['icon'] ?? '❓' }}
                                            {{ $region['type'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $rx }}%, {{ $ry }}%</td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $rw }}% × {{ $rh }}%</td>
                                    <td class="py-2 px-3 text-xs truncate max-w-[200px]">{{ $region['default_content'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
