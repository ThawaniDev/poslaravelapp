{{-- POS Section Container --}}
@props([
    'title' => null,
    'action' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @if($title)
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ $title }}</h3>
            @if($action)
                {{ $action }}
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
