@props(['title', 'subtitle' => null, 'back' => null, 'actions' => null])

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}"
                class="mb-2 inline-flex items-center gap-1 text-sm text-stone-500 transition hover:text-brand-600">
                <x-icon name="chevron-right" class="h-4 w-4" />
                بازگشت
            </a>
        @endif

        <h1 class="text-2xl font-black text-stone-900">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($actions)
        <div class="flex flex-wrap items-center gap-2 no-print">{{ $actions }}</div>
    @endif
</div>
