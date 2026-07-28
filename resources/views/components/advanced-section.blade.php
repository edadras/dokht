@props(['title' => 'تنظیمات پیشرفته', 'description' => null, 'open' => false])

{{-- بخش بازشو برای پنهان کردن جزئیات حرفه‌ای از کاربر ساده --}}
<div x-data="{ open: @js($open) }" {{ $attributes->merge(['class' => 'rounded-2xl border border-stone-200 bg-white']) }}>
    <button type="button" @click="open = !open"
        class="flex w-full items-center gap-3 px-5 py-4 text-start transition hover:bg-stone-50">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-500">
            <x-icon name="settings" class="h-5 w-5" />
        </span>

        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-stone-800">{{ $title }}</span>
            @if ($description)
                <span class="block text-xs text-stone-500">{{ $description }}</span>
            @endif
        </span>

        <x-icon name="chevron-down" class="h-5 w-5 shrink-0 text-stone-400 transition"
            x-bind:class="open && 'rotate-180'" />
    </button>

    <div x-cloak x-show="open" x-collapse class="border-t border-stone-100 px-5 py-5">
        {{ $slot }}
    </div>
</div>
