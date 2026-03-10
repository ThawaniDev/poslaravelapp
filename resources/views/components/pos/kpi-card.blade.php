{{-- POS KPI / Stat Card --}}
@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'trend' => null,
    'trendLabel' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-100 p-5']) }}>
    @if($icon)
        <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center mb-3">
            <x-filament::icon :icon="$icon" class="w-5 h-5 text-[#FD8209]" />
        </div>
    @endif

    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>

    @if($trend !== null)
        <div class="mt-2 flex items-center gap-1 text-xs font-medium">
            @if($trend >= 0)
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                <span class="text-emerald-600">+{{ number_format($trend, 1) }}%</span>
            @else
                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                <span class="text-red-600">{{ number_format($trend, 1) }}%</span>
            @endif
            @if($trendLabel)
                <span class="text-slate-400">{{ $trendLabel }}</span>
            @endif
        </div>
    @endif
</div>
