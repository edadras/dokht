@props(['color' => 'slate', 'icon' => null])

@php
    $colors = [
        'slate' => 'bg-stone-100 text-stone-700',
        'gray' => 'bg-stone-200 text-stone-700',
        'brand' => 'bg-brand-50 text-brand-700',
        'amber' => 'bg-amber-100 text-amber-800',
        'sky' => 'bg-sky-100 text-sky-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'rose' => 'bg-rose-100 text-rose-700',
        'clay' => 'bg-clay-100 text-clay-700',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium '.($colors[$color] ?? $colors['slate']),
    ]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="h-3.5 w-3.5" />
    @endif
    {{ $slot }}
</span>
