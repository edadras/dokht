@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])

{{-- پنجره ساده؛ با ارسال رویداد open-modal-{name} باز می‌شود --}}
<div x-data="{ open: false }" x-cloak @open-modal-{{ $name }}.window="open = true"
    @keydown.escape.window="open = false" x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-stone-900/40 backdrop-blur-sm" @click="open = false"></div>

    <div class="relative flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition
            class="w-full {{ $maxWidth }} overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl">
            @if ($title)
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <h3 class="font-bold">{{ $title }}</h3>
                    <button type="button" @click="open = false" class="rounded-lg p-1 text-stone-400 hover:bg-stone-100">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
            @endif

            <div class="p-5">{{ $slot }}</div>
        </div>
    </div>
</div>
