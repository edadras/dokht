@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
    'type' => 'submit',
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 border-transparent',
        'secondary' => 'bg-white text-stone-700 hover:bg-stone-50 border-stone-300',
        'soft' => 'bg-brand-50 text-brand-700 hover:bg-brand-100 border-transparent',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 border-transparent',
        'ghost' => 'bg-transparent text-stone-600 hover:bg-stone-100 border-transparent',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2.5 text-sm gap-2',
        'lg' => 'px-6 py-3 text-base gap-2',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-xl border font-semibold transition disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </button>
@endif
