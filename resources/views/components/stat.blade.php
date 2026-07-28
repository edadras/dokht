@props(['label', 'value', 'icon' => null, 'hint' => null, 'color' => 'brand', 'href' => null])

@php
    $colors = [
        'brand' => 'bg-brand-50 text-brand-600',
        'clay' => 'bg-clay-50 text-clay-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'rose' => 'bg-rose-50 text-rose-600',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    class="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition {{ $href ? 'hover:border-brand-300 hover:shadow' : '' }}">
    @if ($icon)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $colors[$color] ?? $colors['brand'] }}">
            <x-icon :name="$icon" class="h-6 w-6" />
        </span>
    @endif

    <div class="min-w-0">
        <p class="text-xs text-stone-500">{{ $label }}</p>
        <p class="text-xl font-black text-stone-900">{{ $value }}</p>
        @if ($hint)
            <p class="text-xs text-stone-400">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
