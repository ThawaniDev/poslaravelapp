{{-- POS Empty State --}}
@props([
    'title' => 'No data found',
    'subtitle' => null,
    'icon' => 'heroicon-o-inbox',
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <x-filament::icon :icon="$icon" class="w-16 h-16 text-orange-200 mb-4" />
    <h4 class="text-lg font-semibold text-slate-900">{{ $title }}</h4>
    @if($subtitle)
        <p class="mt-1 text-sm text-slate-500 max-w-sm">{{ $subtitle }}</p>
    @endif
    @if($actionLabel)
        <a href="{{ $actionUrl ?? '#' }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[#FD8209] text-white rounded-lg text-sm font-semibold hover:bg-[#EA6C08] transition">
            {{ $actionLabel }}
        </a>
    @endif
</div>
