@props(['type' => 'info', 'title' => null])

@php
    $styles = [
        'success' => ['class' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'icon' => 'check-circle'],
        'error' => ['class' => 'border-rose-200 bg-rose-50 text-rose-800', 'icon' => 'alert'],
        'warning' => ['class' => 'border-amber-200 bg-amber-50 text-amber-900', 'icon' => 'alert'],
        'info' => ['class' => 'border-sky-200 bg-sky-50 text-sky-800', 'icon' => 'info'],
        'tip' => ['class' => 'border-brand-200 bg-brand-50 text-brand-800', 'icon' => 'sparkles'],
    ];

    $style = $styles[$type] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border p-4 text-sm '.$style['class']]) }}>
    <x-icon :name="$style['icon']" class="mt-0.5 h-5 w-5 shrink-0" />
    <div class="min-w-0 flex-1 space-y-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="font-medium">{{ $slot }}</div>
    </div>
</div>
