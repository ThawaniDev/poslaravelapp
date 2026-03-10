{{-- POS Status Badge --}}
@props([
    'variant' => 'neutral',  // success | warning | error | info | neutral | primary
    'label' => '',
    'small' => false,
])

@php
    $classes = match($variant) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'error'   => 'bg-red-100 text-red-700',
        'info'    => 'bg-blue-100 text-blue-700',
        'primary' => 'bg-orange-100 text-orange-700',
        default   => 'bg-slate-100 text-slate-600',
    };
    $size = $small ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-1 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-semibold $classes $size"]) }}>
    {{ $label ?: $slot }}
</span>
