@props(['label' => null, 'value' => 0, 'max' => 100, 'color' => null, 'showValue' => true])

@php
    $percent = $max > 0 ? max(0, min(100, ($value / $max) * 100)) : 0;

    $color ??= match (true) {
        $percent >= 80 => 'bg-emerald-500',
        $percent >= 60 => 'bg-brand-500',
        $percent >= 40 => 'bg-amber-500',
        default => 'bg-rose-500',
    };
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label || $showValue)
        <div class="flex items-center justify-between text-xs">
            <span class="font-medium text-stone-600">{{ $label }}</span>
            @if ($showValue)
                <span class="font-semibold text-stone-800">{{ \App\Support\Format::percent($percent) }}</span>
            @endif
        </div>
    @endif

    <div class="h-2 w-full overflow-hidden rounded-full bg-stone-100">
        <div class="h-full rounded-full transition-all {{ $color }}" style="width: {{ $percent }}%"></div>
    </div>
</div>
