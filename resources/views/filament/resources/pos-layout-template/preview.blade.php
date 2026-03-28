<x-filament-panels::page>
    @php
        $record = $this->record;
        $config = $record->config ?? [];
        $placements = $record->widgetPlacements ?? collect();
        $canvasCols = $record->canvas_columns ?? 24;
        $canvasRows = $record->canvas_rows ?? 16;
        $canvasGap = $record->canvas_gap_px ?? 4;
        $canvasPad = $record->canvas_padding_px ?? 8;
        $breakpoints = $record->breakpoints ?? [];

        $layoutType = $config['layout_type'] ?? 'grid';
        $cartPosition = $config['cart_position'] ?? 'right';
        $cartWidth = $config['cart_width'] ?? 35;
        $showCategories = $config['show_categories'] ?? true;
        $categoryStyle = $config['category_style'] ?? 'tabs';
        $productDisplay = $config['product_display'] ?? 'grid';
        $productColumns = $config['product_columns'] ?? 4;
        $showImages = $config['show_images'] ?? true;
        $quickActions = $config['quick_actions'] ?? [];
        $paymentButtons = $config['payment_buttons'] ?? [];
        $specialFeatures = $config['special_features'] ?? [];

        // Color palette per widget category
        $categoryColors = [
            'navigation' => ['bg' => 'bg-blue-50 dark:bg-blue-950/40', 'border' => 'border-blue-400 dark:border-blue-600', 'text' => 'text-blue-700 dark:text-blue-300', 'badge' => 'info'],
            'product'    => ['bg' => 'bg-green-50 dark:bg-green-950/40', 'border' => 'border-green-400 dark:border-green-600', 'text' => 'text-green-700 dark:text-green-300', 'badge' => 'success'],
            'cart'       => ['bg' => 'bg-amber-50 dark:bg-amber-950/40', 'border' => 'border-amber-400 dark:border-amber-600', 'text' => 'text-amber-700 dark:text-amber-300', 'badge' => 'warning'],
            'payment'    => ['bg' => 'bg-purple-50 dark:bg-purple-950/40', 'border' => 'border-purple-400 dark:border-purple-600', 'text' => 'text-purple-700 dark:text-purple-300', 'badge' => 'primary'],
            'display'    => ['bg' => 'bg-cyan-50 dark:bg-cyan-950/40', 'border' => 'border-cyan-400 dark:border-cyan-600', 'text' => 'text-cyan-700 dark:text-cyan-300', 'badge' => 'gray'],
            'action'     => ['bg' => 'bg-rose-50 dark:bg-rose-950/40', 'border' => 'border-rose-400 dark:border-rose-600', 'text' => 'text-rose-700 dark:text-rose-300', 'badge' => 'danger'],
        ];
        $defaultColors = ['bg' => 'bg-gray-50 dark:bg-gray-800', 'border' => 'border-gray-400 dark:border-gray-600', 'text' => 'text-gray-700 dark:text-gray-300', 'badge' => 'gray'];

        $sampleCategories = ['All', 'Beverages', 'Snacks', 'Fresh', 'Dairy'];
        $sampleProducts = [
            ['name' => 'Cappuccino', 'price' => '1.800'],
            ['name' => 'Latte', 'price' => '2.000'],
            ['name' => 'Espresso', 'price' => '1.200'],
            ['name' => 'Croissant', 'price' => '0.650'],
            ['name' => 'Muffin', 'price' => '0.800'],
            ['name' => 'Water 500ml', 'price' => '0.200'],
            ['name' => 'Orange Juice', 'price' => '1.500'],
            ['name' => 'Sandwich', 'price' => '1.800'],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Header Info --}}
        <x-filament::section>
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $record->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->name_ar }}</p>
                    @if($record->description)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 max-w-2xl">{{ $record->description }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($record->businessType)
                        <x-filament::badge color="primary">{{ $record->businessType->name }}</x-filament::badge>
                    @endif
                    <x-filament::badge color="gray">{{ $record->layout_key }}</x-filament::badge>
                    <x-filament::badge color="info">v{{ $record->version ?? '1.0.0' }}</x-filament::badge>
                    @if($record->is_locked)
                        <x-filament::badge color="danger" icon="heroicon-o-lock-closed">{{ __('ui.locked') }}</x-filament::badge>
                    @endif
                    @if($record->is_default)
                        <x-filament::badge color="warning">{{ __('ui.default') }}</x-filament::badge>
                    @endif
                    <x-filament::badge :color="$record->is_active ? 'success' : 'danger'">
                        {{ $record->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-filament::badge>
                    @if($record->published_at)
                        <x-filament::badge color="success" icon="heroicon-o-check-circle">
                            {{ __('ui.published') }} {{ $record->published_at->diffForHumans() }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Canvas Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $canvasCols }}×{{ $canvasRows }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('ui.canvas_grid') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $canvasGap }}px</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('ui.grid_gap') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $canvasPad }}px</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('ui.padding') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $placements->count() }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('ui.widgets') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ str_replace('_', ' ', ucfirst($layoutType)) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('ui.layout_type') }}</div>
            </div>
        </div>

        {{-- Widget Canvas Visualization --}}
        @if($placements->count() > 0)
            <x-filament::section heading="{{ __('ui.widget_canvas') }}">
                <x-slot name="description">
                    {{ $canvasCols }} columns × {{ $canvasRows }} rows &mdash; {{ __('ui.each_cell_one_grid_unit') }}
                </x-slot>

                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 overflow-x-auto">
                    <div class="mx-auto" style="min-width: 600px; max-width: 960px;">
                        {{-- Grid visualization --}}
                        <div style="display: grid;
                                    grid-template-columns: repeat({{ $canvasCols }}, 1fr);
                                    grid-template-rows: repeat({{ $canvasRows }}, minmax(30px, 1fr));
                                    gap: {{ max(2, (int)($canvasGap / 2)) }}px;
                                    padding: {{ max(4, (int)($canvasPad / 2)) }}px;
                                    border: 1px solid rgba(148,163,184,0.25);
                                    border-radius: 0.5rem;
                                    background-image:
                                        repeating-linear-gradient(90deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent calc(100% / {{ $canvasCols }})),
                                        repeating-linear-gradient(0deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent calc(100% / {{ $canvasRows }}));
                                    background-color: rgba(148,163,184,0.03);">
                            @foreach($placements->sortBy('z_index') as $wp)
                                @php
                                    $cat = $wp->widget?->category?->value ?? 'default';
                                    $colors = $categoryColors[$cat] ?? $defaultColors;
                                @endphp
                                <div class="rounded-lg border-2 {{ $colors['bg'] }} {{ $colors['border'] }} flex flex-col items-center justify-center text-center transition-all hover:shadow-lg hover:scale-[1.02] cursor-default {{ !$wp->is_visible ? 'opacity-40 border-dashed' : '' }}"
                                     style="grid-column: {{ $wp->grid_x + 1 }} / span {{ $wp->grid_w }};
                                            grid-row: {{ $wp->grid_y + 1 }} / span {{ $wp->grid_h }};
                                            z-index: {{ $wp->z_index }};
                                            min-height: 30px;"
                                     title="{{ $wp->widget?->name ?? $wp->instance_key }}&#10;Position: col {{ $wp->grid_x }}, row {{ $wp->grid_y }}&#10;Size: {{ $wp->grid_w }}×{{ $wp->grid_h }}&#10;Z-Index: {{ $wp->z_index }}{{ !$wp->is_visible ? '&#10;(Hidden)' : '' }}">
                                    @if($wp->widget?->icon)
                                        @svg($wp->widget->icon, 'w-5 h-5 ' . $colors['text'] . ' mb-0.5')
                                    @endif
                                    <div class="text-[11px] font-semibold {{ $colors['text'] }} leading-tight truncate w-full px-1">
                                        {{ $wp->widget?->name ?? $wp->instance_key }}
                                    </div>
                                    <div class="text-[9px] text-gray-400 leading-tight mt-0.5">
                                        {{ $wp->grid_w }}×{{ $wp->grid_h }}
                                    </div>
                                    @if(!$wp->is_visible)
                                        <div class="text-[8px] text-red-400 italic">hidden</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Column axis labels --}}
                        <div style="display: grid; grid-template-columns: repeat({{ $canvasCols }}, 1fr); padding: 0 {{ max(4, (int)($canvasPad / 2)) }}px; gap: {{ max(2, (int)($canvasGap / 2)) }}px;" class="mt-1">
                            @for($i = 0; $i < $canvasCols; $i++)
                                <div class="text-[8px] text-gray-400 text-center">{{ $i }}</div>
                            @endfor
                        </div>
                    </div>
                </div>

                {{-- Widget category legend --}}
                <div class="mt-4 flex flex-wrap gap-4">
                    @foreach($categoryColors as $catName => $catColors)
                        @if($placements->contains(fn($wp) => ($wp->widget?->category?->value ?? '') === $catName))
                            <div class="flex items-center gap-2 text-xs">
                                <div class="w-3.5 h-3.5 rounded border-2 {{ $catColors['bg'] }} {{ $catColors['border'] }}"></div>
                                <span class="text-gray-600 dark:text-gray-400 capitalize">{{ $catName }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Widget Placement Details Table --}}
        @if($placements->count() > 0)
            <x-filament::section heading="{{ __('ui.widget_placements') }}" collapsible>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b dark:border-gray-700">
                                <th class="py-2 px-3 text-gray-500">#</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.widget') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.category') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.position') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.size') }}</th>
                                <th class="py-2 px-3 text-gray-500">Z-Index</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.visible') }}</th>
                                <th class="py-2 px-3 text-gray-500">{{ __('ui.instance_key') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($placements->sortBy('z_index') as $idx => $wp)
                                @php $cat = $wp->widget?->category?->value ?? 'default'; @endphp
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2 px-3 text-gray-400">{{ $idx + 1 }}</td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            @if($wp->widget?->icon)
                                                @svg($wp->widget->icon, 'w-4 h-4 text-gray-500')
                                            @endif
                                            <div>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $wp->widget?->name ?? '-' }}</span>
                                                @if($wp->widget?->name_ar)
                                                    <span class="text-xs text-gray-400 ms-1">({{ $wp->widget->name_ar }})</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <x-filament::badge :color="($categoryColors[$cat] ?? $defaultColors)['badge']" size="sm">
                                            {{ $cat }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-2 px-3 font-mono text-xs">col {{ $wp->grid_x }}, row {{ $wp->grid_y }}</td>
                                    <td class="py-2 px-3 font-mono text-xs">{{ $wp->grid_w }} × {{ $wp->grid_h }}</td>
                                    <td class="py-2 px-3 text-center font-mono text-xs">{{ $wp->z_index }}</td>
                                    <td class="py-2 px-3 text-center">
                                        @if($wp->is_visible)
                                            <span class="text-green-500">✓</span>
                                        @else
                                            <span class="text-red-400">✗</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 font-mono text-xs text-gray-400">{{ $wp->instance_key }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Layout Type Badges --}}
        <div class="flex gap-3 flex-wrap">
            <x-filament::badge color="info" size="lg">
                Layout: {{ str_replace('_', ' ', ucfirst($layoutType)) }}
            </x-filament::badge>
            <x-filament::badge color="info" size="lg">
                Cart: {{ ucfirst($cartPosition) }} ({{ $cartWidth }}%)
            </x-filament::badge>
            <x-filament::badge color="info" size="lg">
                Products: {{ ucfirst($productDisplay) }} ({{ $productColumns }} cols)
            </x-filament::badge>
            @if($showCategories)
                <x-filament::badge color="info" size="lg">
                    Categories: {{ ucfirst($categoryStyle) }}
                </x-filament::badge>
            @endif
        </div>

        {{-- POS Screen Mockup --}}
        <x-filament::section heading="{{ __('ui.screen_mockup') }}" collapsible>
            <div class="flex justify-center">
                <div class="w-full max-w-4xl">
                    <div class="text-xs text-gray-400 text-center mb-2">{{ __('ui.pos_layout_preview') }}</div>

                    <div class="rounded-xl overflow-hidden shadow-2xl border-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900"
                         style="aspect-ratio: 16/10;">

                        @if($cartPosition === 'bottom')
                            {{-- Vertical layout: products on top, cart on bottom --}}
                            <div class="flex flex-col h-full">
                                <div class="flex-1 flex overflow-hidden">
                                    @if($showCategories && $categoryStyle === 'sidebar')
                                        <div class="w-24 bg-gray-200 dark:bg-gray-800 p-2 space-y-1 border-r dark:border-gray-700">
                                            @foreach(array_slice($sampleCategories, 0, 5) as $i => $cat)
                                                <div class="text-xs px-2 py-1.5 rounded {{ $i === 0 ? 'bg-blue-500 text-white' : 'text-gray-600 dark:text-gray-400' }}">{{ $cat }}</div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex-1 p-3">
                                        @if($showCategories && $categoryStyle === 'tabs')
                                            <div class="flex gap-1 mb-3 overflow-hidden">
                                                @foreach($sampleCategories as $i => $cat)
                                                    <div class="text-xs px-3 py-1 rounded-full whitespace-nowrap {{ $i === 0 ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">{{ $cat }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($showCategories && $categoryStyle === 'icons')
                                            <div class="flex gap-2 mb-3">
                                                @foreach(array_slice($sampleCategories, 0, 5) as $i => $cat)
                                                    <div class="text-center">
                                                        <div class="w-8 h-8 rounded-lg mx-auto {{ $i === 0 ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-700' }} flex items-center justify-center">
                                                            <span class="text-xs {{ $i === 0 ? 'text-white' : 'text-gray-500' }}">{{ substr($cat, 0, 1) }}</span>
                                                        </div>
                                                        <div class="text-[8px] mt-0.5 text-gray-500 truncate w-10">{{ $cat }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @include('filament.resources.pos-layout-template._product-grid', ['productDisplay' => $productDisplay, 'productColumns' => $productColumns, 'showImages' => $showImages, 'sampleProducts' => $sampleProducts])
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-gray-800 border-t dark:border-gray-700 p-3" style="height: {{ $cartWidth }}%;">
                                    @include('filament.resources.pos-layout-template._cart-preview', ['paymentButtons' => $paymentButtons])
                                </div>
                            </div>
                        @else
                            {{-- Horizontal layout: products left, cart right (or floating) --}}
                            <div class="flex h-full">
                                @if($showCategories && $categoryStyle === 'sidebar')
                                    <div class="w-24 bg-gray-200 dark:bg-gray-800 p-2 space-y-1 border-r dark:border-gray-700">
                                        @foreach(array_slice($sampleCategories, 0, 5) as $i => $cat)
                                            <div class="text-xs px-2 py-1.5 rounded {{ $i === 0 ? 'bg-blue-500 text-white' : 'text-gray-600 dark:text-gray-400' }}">{{ $cat }}</div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex-1 p-3 overflow-hidden">
                                    @if($showCategories && $categoryStyle === 'tabs')
                                        <div class="flex gap-1 mb-3 overflow-hidden">
                                            @foreach($sampleCategories as $i => $cat)
                                                <div class="text-xs px-3 py-1 rounded-full whitespace-nowrap {{ $i === 0 ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">{{ $cat }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($showCategories && $categoryStyle === 'icons')
                                        <div class="flex gap-2 mb-3">
                                            @foreach(array_slice($sampleCategories, 0, 5) as $i => $cat)
                                                <div class="text-center">
                                                    <div class="w-8 h-8 rounded-lg mx-auto {{ $i === 0 ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-700' }} flex items-center justify-center">
                                                        <span class="text-xs {{ $i === 0 ? 'text-white' : 'text-gray-500' }}">{{ substr($cat, 0, 1) }}</span>
                                                    </div>
                                                    <div class="text-[8px] mt-0.5 text-gray-500 truncate w-10">{{ $cat }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @include('filament.resources.pos-layout-template._product-grid', ['productDisplay' => $productDisplay, 'productColumns' => $productColumns, 'showImages' => $showImages, 'sampleProducts' => $sampleProducts])
                                </div>

                                @if($cartPosition === 'floating')
                                    <div class="absolute right-6 top-16 bottom-6 bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-3 border dark:border-gray-700"
                                         style="width: {{ $cartWidth }}%;">
                                        @include('filament.resources.pos-layout-template._cart-preview', ['paymentButtons' => $paymentButtons])
                                    </div>
                                @else
                                    <div class="bg-white dark:bg-gray-800 border-l dark:border-gray-700 p-3"
                                         style="width: {{ $cartWidth }}%;">
                                        @include('filament.resources.pos-layout-template._cart-preview', ['paymentButtons' => $paymentButtons])
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Quick Actions --}}
        @if(!empty($quickActions))
            <x-filament::section heading="{{ __('ui.quick_actions') }}">
                <div class="flex gap-2 flex-wrap">
                    @foreach($quickActions as $action)
                        <x-filament::badge color="gray" size="lg">{{ $action }}</x-filament::badge>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Special Features --}}
        @if(!empty(array_filter($specialFeatures)))
            <x-filament::section heading="{{ __('ui.special_features') }}">
                <div class="flex gap-3 flex-wrap">
                    @foreach($specialFeatures as $feature => $enabled)
                        @if($enabled)
                            <x-filament::badge color="success" size="lg">
                                {{ str_replace('_', ' ', ucfirst($feature)) }}
                            </x-filament::badge>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Breakpoints --}}
        @if(!empty($breakpoints))
            <x-filament::section heading="{{ __('ui.breakpoints') }}" collapsible collapsed>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($breakpoints as $bp => $bpConfig)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-center border dark:border-gray-700">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $bp }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                @if(is_array($bpConfig))
                                    @foreach($bpConfig as $k => $v)
                                        {{ $k }}: {{ is_bool($v) ? ($v ? '✓' : '✗') : $v }}<br>
                                    @endforeach
                                @else
                                    {{ $bpConfig }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Configuration Summary --}}
        <x-filament::section heading="{{ __('ui.configuration_summary') }}" collapsible collapsed>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-500">{{ __('ui.layout_type') }}:</span> {{ $layoutType }}</div>
                <div><span class="text-gray-500">{{ __('ui.cart_position') }}:</span> {{ $cartPosition }}</div>
                <div><span class="text-gray-500">{{ __('ui.cart_width') }}:</span> {{ $cartWidth }}%</div>
                <div><span class="text-gray-500">{{ __('ui.show_categories') }}:</span> {{ $showCategories ? '✓' : '✗' }}</div>
                <div><span class="text-gray-500">{{ __('ui.category_style') }}:</span> {{ $categoryStyle }}</div>
                <div><span class="text-gray-500">{{ __('ui.product_display') }}:</span> {{ $productDisplay }}</div>
                <div><span class="text-gray-500">{{ __('ui.product_columns') }}:</span> {{ $productColumns }}</div>
                <div><span class="text-gray-500">{{ __('ui.show_images') }}:</span> {{ $showImages ? '✓' : '✗' }}</div>
                <div><span class="text-gray-500">{{ __('ui.canvas_columns') }}:</span> {{ $canvasCols }}</div>
                <div><span class="text-gray-500">{{ __('ui.canvas_rows') }}:</span> {{ $canvasRows }}</div>
                <div><span class="text-gray-500">{{ __('ui.canvas_gap') }}:</span> {{ $canvasGap }}px</div>
                <div><span class="text-gray-500">{{ __('ui.canvas_padding') }}:</span> {{ $canvasPad }}px</div>
                @if(!empty($paymentButtons))
                    <div class="col-span-2 md:col-span-3"><span class="text-gray-500">{{ __('ui.payment_buttons') }}:</span> {{ implode(', ', $paymentButtons) }}</div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
