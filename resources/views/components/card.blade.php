@props(['title' => null, 'subtitle' => null, 'icon' => null, 'padding' => 'p-5', 'actions' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-stone-200 bg-white shadow-sm']) }}>
    @if ($title || $actions)
        <div class="flex items-start gap-3 border-b border-stone-100 px-5 py-4">
            @if ($icon)
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <x-icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif

            <div class="min-w-0 flex-1">
                @if ($title)
                    <h2 class="font-bold text-stone-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p>
                @endif
            </div>

            @if ($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">{{ $slot }}</div>
</div>
