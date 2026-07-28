@props(['href' => '#', 'active' => false, 'icon' => null, 'badge' => null])

<a href="{{ $href }}"
    @class([
        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
        'bg-brand-50 text-brand-700' => $active,
        'text-stone-600 hover:bg-stone-100 hover:text-stone-900' => ! $active,
    ])>
    @if ($icon)
        <x-icon :name="$icon" @class([
            'h-5 w-5 shrink-0',
            'text-brand-600' => $active,
            'text-stone-400 group-hover:text-stone-600' => ! $active,
        ]) />
    @endif

    <span class="truncate">{{ $slot }}</span>

    @if ($badge)
        <span class="ms-auto rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">{{ $badge }}</span>
    @endif
</a>
