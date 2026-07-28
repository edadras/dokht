@props(['icon' => 'box', 'title' => 'چیزی اینجا نیست', 'description' => null, 'action' => null])

<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-14 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-stone-400">
        <x-icon :name="$icon" class="h-7 w-7" />
    </span>

    <p class="mt-4 font-bold text-stone-800">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-stone-500">{{ $description }}</p>
    @endif

    @if ($action)
        <div class="mt-5">{{ $action }}</div>
    @endif
</div>
