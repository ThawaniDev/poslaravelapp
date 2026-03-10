{{-- POS Card (base wrapper) --}}
@props([
    'padding' => 'p-4',
])

<div {{ $attributes->merge(['class' => "bg-white rounded-xl border border-slate-100 $padding"]) }}>
    {{ $slot }}
</div>
