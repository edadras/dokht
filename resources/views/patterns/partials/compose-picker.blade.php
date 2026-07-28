{{--
    یک انتخابگر تصویری برای یک نقش (لباس کامل، بالاتنه، آستین، پایین‌تنه، یقه).

    با ده‌ها گزینه، فهرست کوتاه می‌ماند: تا $limit کارت اول نشان داده می‌شود، جستجو
    همه را می‌کاود و دکمه «نمایش همه» بقیه را باز می‌کند.
--}}
@php
    $enabled = $enabled ?? 'true';
    $limit = $limit ?? 8;
    $count = count($items);
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h3 class="flex items-center gap-2 text-sm font-bold text-stone-800">
                <x-icon :name="$icon" class="h-4 w-4 text-brand-500" />
                {{ $title }}
                <x-badge color="slate">{{ \App\Support\Jalali::digits((string) $count) }} مدل</x-badge>
            </h3>
            @if (! empty($subtitle))
                <p class="mt-0.5 text-xs text-stone-500">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <label class="sr-only" for="search-{{ $group }}">جستجو در {{ $title }}</label>
            <input id="search-{{ $group }}" type="search" x-model="q.{{ $group }}" placeholder="جستجوی نام مدل…"
                class="w-44 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">

            @if ($count > $limit)
                <button type="button" @click="showAll.{{ $group }} = ! showAll.{{ $group }}"
                    class="rounded-xl border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:border-brand-400 hover:text-brand-700">
                    <span x-show="! showAll.{{ $group }}">نمایش همه ({{ \App\Support\Jalali::digits((string) $count) }})</span>
                    <span x-show="showAll.{{ $group }}" x-cloak>فهرست کوتاه</span>
                </button>
            @endif
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($items as $key => $item)
            <label class="cursor-pointer"
                x-show="visible('{{ $group }}', {{ $loop->index }}, {{ $limit }}, '{{ $key }}')"
                @if (! empty($item['broken'])) title="این مدل فعلاً ساخته نمی‌شود." @endif>
                <input type="radio" name="{{ $group }}" value="{{ $key }}" class="peer sr-only"
                    x-model="base.{{ $group }}" x-bind:disabled="! ({{ $enabled }})"
                    @checked((string) ($selected ?? '') === (string) $key)
                    @if (! empty($item['broken'])) disabled @endif>

                <div @class([
                    'flex h-full flex-col rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300',
                    'opacity-50' => ! empty($item['broken']),
                ])>
                    <div class="flex h-24 items-center justify-center overflow-hidden rounded-xl bg-stone-50 [&>svg]:h-full [&>svg]:w-auto">
                        @if (! empty($item['thumbnail']))
                            {!! $item['thumbnail'] !!}
                        @else
                            <span class="flex flex-col items-center gap-1 text-stone-400">
                                <x-icon name="{{ empty($item['broken']) ? 'x' : 'alert' }}" class="h-6 w-6" />
                                <span class="text-xs">{{ empty($item['broken']) ? 'بدون این قطعه' : 'فعلاً در دسترس نیست' }}</span>
                            </span>
                        @endif
                    </div>

                    <p class="mt-2.5 text-sm font-bold text-stone-900">{{ $item['label'] }}</p>

                    @if (! empty($item['hint']))
                        <p class="mt-0.5 text-xs leading-5 text-stone-500">{{ $item['hint'] }}</p>
                    @endif
                </div>
            </label>
        @endforeach
    </div>

    <p x-show="hidden('{{ $group }}', {{ $count }}, {{ $limit }})" x-cloak class="text-xs text-stone-400">
        بقیه مدل‌ها با جستجو یا دکمه «نمایش همه» پیدا می‌شوند.
    </p>

    <p x-show="q.{{ $group }} && ! anyVisible('{{ $group }}')" x-cloak class="text-xs font-medium text-amber-700">
        هیچ مدلی با این نام پیدا نشد.
    </p>

    @error($group)
        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>
