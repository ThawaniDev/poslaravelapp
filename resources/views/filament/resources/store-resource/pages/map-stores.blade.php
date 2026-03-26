<x-filament-panels::page>
    <div>

    {{-- ── Stats Cards ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        {{-- Total --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-orange-50 p-2 dark:bg-orange-500/10">
                    <x-heroicon-o-building-storefront class="h-5 w-5 text-orange-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_total') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($totalStores) }}</p>
                </div>
            </div>
        </div>

        {{-- Mapped --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-blue-50 p-2 dark:bg-blue-500/10">
                    <x-heroicon-o-map-pin class="h-5 w-5 text-blue-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_mapped') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($mappedStores) }}</p>
                </div>
            </div>
        </div>

        {{-- Unmapped --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-yellow-50 p-2 dark:bg-yellow-500/10">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_unmapped') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($unmappedStores) }}</p>
                </div>
            </div>
        </div>

        {{-- Active --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-green-50 p-2 dark:bg-green-500/10">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-green-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_active') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($activeStores) }}</p>
                </div>
            </div>
        </div>

        {{-- Inactive --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-red-50 p-2 dark:bg-red-500/10">
                    <x-heroicon-o-x-circle class="h-5 w-5 text-red-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_inactive') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($inactiveStores) }}</p>
                </div>
            </div>
        </div>

        {{-- Cities --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-500/10">
                    <x-heroicon-o-globe-alt class="h-5 w-5 text-purple-500" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin_dashboard.store_map_cities') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $cityCounts->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filter Bar + Map ───────────────────────────────────────── --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        {{-- Filter toolbar --}}
        <div class="border-b border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex gap-2 flex-wrap" id="map-filters">
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-gray-200 bg-[#FD8209] text-white cursor-pointer transition-all select-none dark:bg-[#FD8209] dark:border-[#FD8209]" data-filter="all">
                        {{ __('admin_dashboard.store_map_filter_all') }}
                        <span class="px-1.5 rounded-full text-[0.7rem]" style="background:rgba(255,255,255,.25)">{{ $mappedStores }}</span>
                    </button>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-gray-200 bg-white text-gray-700 cursor-pointer transition-all select-none dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 hover:border-[#FD8209]" data-filter="active">
                        <span class="inline-block w-2.5 h-2.5 rounded-full mr-0.5" style="background:#22c55e;"></span>
                        {{ __('admin_dashboard.store_map_filter_active') }}
                        <span class="px-1.5 rounded-full text-[0.7rem]" style="background:rgba(0,0,0,.1)">{{ $activeStores }}</span>
                    </button>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-gray-200 bg-white text-gray-700 cursor-pointer transition-all select-none dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 hover:border-[#FD8209]" data-filter="inactive">
                        <span class="inline-block w-2.5 h-2.5 rounded-full mr-0.5" style="background:#ef4444;"></span>
                        {{ __('admin_dashboard.store_map_filter_inactive') }}
                        <span class="px-1.5 rounded-full text-[0.7rem]" style="background:rgba(0,0,0,.1)">{{ $inactiveStores }}</span>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input type="text" id="map-search" placeholder="{{ __('admin_dashboard.store_map_search_placeholder') }}"
                               class="block w-full sm:w-64 rounded-lg border-gray-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm transition focus:border-orange-500 focus:ring-orange-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:placeholder:text-gray-500">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <x-heroicon-m-magnifying-glass class="h-4 w-4 text-gray-400" />
                        </div>
                    </div>
                    <button type="button" id="map-recenter" title="{{ __('admin_dashboard.store_map_recenter') }}"
                            class="rounded-lg border border-gray-300 bg-white p-2 shadow-sm transition hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:hover:bg-gray-700">
                        <x-heroicon-m-arrows-pointing-in class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </button>
                </div>
            </div>
        </div>

        {{-- Map --}}
        <div class="relative overflow-hidden" style="height: 600px; border-radius: 0 0 0.75rem 0.75rem;">
            <div id="store-map" style="height: 100%; width: 100%; z-index: 1;"></div>

            {{-- Legend --}}
            <div class="absolute bottom-6 left-6 z-[1000] rounded-xl bg-white p-3 shadow-md text-xs leading-relaxed dark:bg-gray-800 dark:text-gray-200">
                <div class="font-semibold mb-1">{{ __('admin_dashboard.store_map_legend') }}</div>
                <div><span class="inline-block w-2.5 h-2.5 rounded-full mr-1.5" style="background: #22c55e;"></span>{{ __('admin_dashboard.store_map_filter_active') }}</div>
                <div><span class="inline-block w-2.5 h-2.5 rounded-full mr-1.5" style="background: #ef4444;"></span>{{ __('admin_dashboard.store_map_filter_inactive') }}</div>
            </div>
        </div>
    </div>

    {{-- ── Side-by-side panels: Top Cities + Business Types ──────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Top Cities --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">{{ __('admin_dashboard.store_map_top_cities') }}</h3>
            <div class="space-y-3">
                @forelse($cityCounts as $city => $count)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-heroicon-m-map-pin class="h-4 w-4 text-gray-400 flex-shrink-0" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $city ?: __('admin_dashboard.store_map_unknown') }}</span>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="w-24 bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-orange-500 h-2 rounded-full" style="width: {{ $mappedStores > 0 ? round(($count / $mappedStores) * 100) : 0 }}%;"></div>
                            </div>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white w-8 text-right">{{ $count }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('admin_dashboard.store_map_no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Business Types --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">{{ __('admin_dashboard.store_map_by_business_type') }}</h3>
            <div class="space-y-3">
                @forelse($businessTypeCounts as $type => $count)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-heroicon-m-tag class="h-4 w-4 text-gray-400 flex-shrink-0" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate capitalize">{{ str_replace('_', ' ', $type) }}</span>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="w-24 bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $mappedStores > 0 ? round(($count / $mappedStores) * 100) : 0 }}%;"></div>
                            </div>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white w-8 text-right">{{ $count }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('admin_dashboard.store_map_no_data') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
    (function() {
        // ── Inject CSS dynamically (avoids <style> tag which breaks Livewire DOMDocument parsing) ──
        function injectCSS() {
            if (document.getElementById('store-map-styles')) return;
            var css = document.createElement('style');
            css.id = 'store-map-styles';
            css.textContent = [
                '.leaflet-popup-content-wrapper{border-radius:.75rem!important;box-shadow:0 10px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1)!important}',
                '.leaflet-popup-content{margin:0!important;padding:0!important;min-width:280px}',
                '.store-popup{padding:1rem;font-family:Cairo,sans-serif}',
                '.store-popup-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem}',
                '.store-popup-name{font-weight:700;font-size:.95rem;color:#1f2937;line-height:1.3}',
                '.dark .store-popup-name{color:#f3f4f6}',
                '.store-popup-badge{display:inline-flex;align-items:center;padding:.125rem .5rem;border-radius:9999px;font-size:.7rem;font-weight:600;white-space:nowrap}',
                '.store-popup-badge.active{background:#dcfce7;color:#166534}',
                '.store-popup-badge.inactive{background:#fee2e2;color:#991b1b}',
                '.store-popup-detail{display:flex;align-items:center;gap:.5rem;padding:.25rem 0;font-size:.8rem;color:#6b7280}',
                '.store-popup-detail svg{width:14px;height:14px;flex-shrink:0;color:#9ca3af}',
                '.store-popup-footer{margin-top:.75rem;padding-top:.75rem;border-top:1px solid #e5e7eb;text-align:center}',
                '.store-popup-link{display:inline-flex;align-items:center;gap:.375rem;padding:.375rem 1rem;background:#FD8209;color:#fff;font-weight:600;font-size:.8rem;border-radius:.5rem;text-decoration:none;transition:background .15s}',
                '.store-popup-link:hover{background:#C2530A;color:#fff}',
            ].join('\n');
            document.head.appendChild(css);
        }

        function loadScript(src, integrity, callback) {
            if (document.querySelector('script[src="' + src + '"]')) { callback(); return; }
            var s = document.createElement('script');
            s.src = src;
            if (integrity) { s.integrity = integrity; s.crossOrigin = ''; }
            s.onload = callback;
            document.head.appendChild(s);
        }
        function loadCSS(href, integrity) {
            if (document.querySelector('link[href="' + href + '"]')) return;
            var l = document.createElement('link');
            l.rel = 'stylesheet'; l.href = href;
            if (integrity) { l.integrity = integrity; l.crossOrigin = ''; }
            document.head.appendChild(l);
        }

        injectCSS();
        loadCSS('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=');
        loadCSS('https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css');
        loadCSS('https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css');
        loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=', function() {
            loadScript('https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', null, initMap);
        });

        function initMap() {
            var markers = @json($markers);
            var defaultCenter = [23.5, 54.0];
            var defaultZoom = 6;

            var map = L.map('store-map', {
                center: defaultCenter,
                zoom: defaultZoom,
                zoomControl: true,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            function createIcon(color) {
                return L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="width:28px;height:28px;background:' + color + ';border:3px solid white;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="white" style="width:14px;height:14px"><path fill-rule="evenodd" d="M4 16.5v-13h-.25a.75.75 0 010-1.5h12.5a.75.75 0 010 1.5H16v13h.25a.75.75 0 010 1.5h-3.5a.75.75 0 01-.75-.75v-2.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v2.5a.75.75 0 01-.75.75h-3.5a.75.75 0 010-1.5H4z" clip-rule="evenodd"/></svg></div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14],
                    popupAnchor: [0, -16],
                });
            }

            var activeIcon = createIcon('#22c55e');
            var inactiveIcon = createIcon('#ef4444');

            var clusterGroup = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                iconCreateFunction: function (cluster) {
                    var count = cluster.getChildCount();
                    var dim = count > 50 ? 48 : (count > 10 ? 42 : 36);
                    var fs = dim > 42 ? 14 : 12;
                    return L.divIcon({
                        html: '<div style="width:' + dim + 'px;height:' + dim + 'px;background:#FD8209;border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:' + fs + 'px;font-family:Cairo,sans-serif">' + count + '</div>',
                        className: 'marker-cluster',
                        iconSize: L.point(dim, dim),
                    });
                },
            });

            var allMarkers = [];
            var statusActiveLabel = @json(__('admin_dashboard.status_active'));
            var statusInactiveLabel = @json(__('admin_dashboard.status_inactive'));
            var viewLabel = @json(__('admin_dashboard.store_map_view_store'));

            markers.forEach(function (store) {
                var icon = store.is_active ? activeIcon : inactiveIcon;
                var statusLabel = store.is_active ? statusActiveLabel : statusInactiveLabel;
                var statusClass = store.is_active ? 'active' : 'inactive';

                var popup = '<div class="store-popup">'
                    + '<div class="store-popup-header"><div>'
                    + '<div class="store-popup-name">' + store.name + '</div>'
                    + (store.name_ar ? '<div style="font-size:0.8rem;color:#9ca3af;margin-top:2px">' + store.name_ar + '</div>' : '')
                    + '</div><span class="store-popup-badge ' + statusClass + '">' + statusLabel + '</span></div>'
                    + '<div class="store-popup-detail"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 7.5h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg><span>' + store.organization + '</span></div>'
                    + '<div class="store-popup-detail"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg><span>' + store.city + (store.address ? ' — ' + store.address : '') + '</span></div>'
                    + '<div class="store-popup-detail"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg><span style="text-transform:capitalize">' + store.business_type.replace(/_/g, ' ') + '</span></div>'
                    + (store.phone ? '<div class="store-popup-detail"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg><span>' + store.phone + '</span></div>' : '')
                    + '<div class="store-popup-detail"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg><span>' + store.created_at + '</span></div>'
                    + '<div class="store-popup-footer"><a href="' + store.view_url + '" class="store-popup-link"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> ' + viewLabel + '</a></div>'
                    + '</div>';

                var marker = L.marker([store.lat, store.lng], { icon: icon })
                    .bindPopup(popup, { maxWidth: 320 });

                marker._storeData = store;
                allMarkers.push(marker);
                clusterGroup.addLayer(marker);
            });

            map.addLayer(clusterGroup);

            if (allMarkers.length > 0) {
                map.fitBounds(L.featureGroup(allMarkers).getBounds().pad(0.1));
            }

            // Filter logic
            var filterBtns = document.querySelectorAll('#map-filters button');
            var activeFilterClass = 'bg-[#FD8209] text-white border-[#FD8209]';
            var inactiveFilterClass = 'bg-white text-gray-700 border-gray-200 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200';

            filterBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(function(b) {
                        b.className = b.className.replace(/bg-\[#FD8209\] text-white border-\[#FD8209\]/g, '').trim();
                        if (!b.className.match(/bg-white/)) {
                            b.className = b.className + ' ' + inactiveFilterClass;
                        }
                    });
                    btn.className = btn.className.replace(/bg-white text-gray-700 border-gray-200|dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200/g, '').trim();
                    btn.className = btn.className + ' ' + activeFilterClass;
                    applyFilters();
                });
            });

            function applyFilters() {
                var activeBtn = document.querySelector('#map-filters button[class*="bg-[#FD8209]"]');
                var activeFilter = activeBtn ? activeBtn.dataset.filter : 'all';
                var searchTerm = (document.getElementById('map-search')?.value || '').toLowerCase().trim();

                clusterGroup.clearLayers();

                allMarkers.forEach(function (marker) {
                    var store = marker._storeData;
                    var show = true;

                    if (activeFilter === 'active' && !store.is_active) show = false;
                    if (activeFilter === 'inactive' && store.is_active) show = false;

                    if (searchTerm && show) {
                        var haystack = [store.name, store.name_ar, store.city, store.organization, store.address, store.business_type]
                            .filter(Boolean).join(' ').toLowerCase();
                        show = haystack.indexOf(searchTerm) !== -1;
                    }

                    if (show) clusterGroup.addLayer(marker);
                });
            }

            var searchTimeout;
            document.getElementById('map-search')?.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilters, 300);
            });

            document.getElementById('map-recenter')?.addEventListener('click', function () {
                if (allMarkers.length > 0) {
                    var visibleMarkers = [];
                    clusterGroup.eachLayer(function(m) { visibleMarkers.push(m); });
                    if (visibleMarkers.length > 0) {
                        map.fitBounds(L.featureGroup(visibleMarkers).getBounds().pad(0.1));
                    } else {
                        map.fitBounds(L.featureGroup(allMarkers).getBounds().pad(0.1));
                    }
                } else {
                    map.setView(defaultCenter, defaultZoom);
                }
            });

            if (markers.length === 0) {
                document.getElementById('store-map').innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#9ca3af">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:64px;height:64px;margin-bottom:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>'
                    + '<p style="font-size:1rem;font-weight:600">{{ __("admin_dashboard.store_map_no_locations") }}</p>'
                    + '<p style="font-size:.875rem">{{ __("admin_dashboard.store_map_no_locations_hint") }}</p>'
                    + '</div>';
            }
        }
    })();
    </script>

    </div>
</x-filament-panels::page>
