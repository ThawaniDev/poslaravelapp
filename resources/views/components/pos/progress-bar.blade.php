{{-- POS Progress Bar --}}
@props([
    'value' => 0,         // 0-100
    'label' => null,
    'showPercent' => true,
    'color' => 'bg-[#FD8209]',
])

<div {{ $attributes->class(['w-full']) }}>
    @if($label || $showPercent)
        <div class="flex items-center justify-between mb-1.5">
            @if($label) <span class="text-xs font-medium text-slate-700">{{ $label }}</span> @endif
            @if($showPercent) <span class="text-xs text-slate-400">{{ $value }}%</span> @endif
        </div>
    @endif

    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
        <div class="{{ $color }} h-full rounded-full transition-all duration-300"
             style="width: {{ min(max($value, 0), 100) }}%"></div>
    </div>
</div>
