{{--
    یک انتخابگر تصویری برای یک نقش (لباس کامل، بالاتنه، آستین، پایین‌تنه، یقه).

    کارت‌ها را مرورگر می‌سازد، نه سرور. با چند ده مدل فرقی نمی‌کرد؛ با هزاران مدل
    فرق دنیاست: اگر همهٔ کارت‌ها در صفحه نوشته شوند — حتی پنهان — مرورگر باید
    هزاران قاب و هزاران تصویر را نگه دارد و صفحه از دست می‌رود.

    پس فهرست هم در صفحه نیست: صفحه فقط با بستهٔ اولِ هر نقش بالا می‌آید و بستهٔ
    بعدی و نتیجهٔ جستجو را از سرور می‌گیرد. یعنی وزن صفحه به تعداد مدل‌ها بستگی
    ندارد. جستجو هم روی *همهٔ* مدل‌ها کار می‌کند، چون سرور جستجو می‌کند نه مرورگر.
    تصویرها هم تنبل‌اند: هر کدام نشانیِ خودش را دارد و مرورگر همان‌هایی را می‌گیرد
    که به چشم می‌آیند.
--}}
@php
    $enabled = $enabled ?? 'true';
@endphp

<div class="space-y-3" x-data="{ group: '{{ $group }}' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h3 class="flex items-center gap-2 text-sm font-bold text-stone-800">
                <x-icon :name="$icon" class="h-4 w-4 text-brand-500" />
                {{ $title }}
                <x-badge color="slate"><span x-text="digits(count(group))"></span> مدل</x-badge>
            </h3>
            @if (! empty($subtitle))
                <p class="mt-0.5 text-xs text-stone-500">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <label class="sr-only" for="search-{{ $group }}">جستجو در {{ $title }}</label>
            <input id="search-{{ $group }}" type="search" x-model="q.{{ $group }}"
                @input="searchList('{{ $group }}')" placeholder="جستجوی نام مدل…"
                class="w-44 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
    </div>

    {{-- کارتِ انتخاب‌شده همیشه در فهرست هست (page() خودش سرِ فهرست می‌گذاردش)،
         پس رادیوی انتخاب‌شده هیچ‌وقت از DOM بیرون نمی‌افتد و فرم خالی نمی‌ماند --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <template x-for="item in page(group)" :key="item.k">
            <label class="cursor-pointer">
                <input type="radio" x-bind:name="group" x-bind:value="item.k" class="peer sr-only"
                    x-model="base[group]" @change="remember(group, item)"
                    x-bind:disabled="! ({{ $enabled }})">

                <div class="flex h-full flex-col rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300">
                    <div class="flex h-24 items-center justify-center overflow-hidden rounded-xl bg-stone-50">
                        <template x-if="thumb(group, item.k)">
                            <img loading="lazy" decoding="async" x-bind:src="thumb(group, item.k)"
                                x-bind:alt="item.l" class="h-full w-auto object-contain">
                        </template>
                        <template x-if="! thumb(group, item.k)">
                            <span class="flex flex-col items-center gap-1 text-stone-400">
                                <x-icon name="x" class="h-6 w-6" />
                                <span class="text-xs">بدون این قطعه</span>
                            </span>
                        </template>
                    </div>

                    <p class="mt-2.5 text-sm font-bold text-stone-900" x-text="item.l"></p>
                    <p class="mt-0.5 text-xs leading-5 text-stone-500" x-show="hint(item)" x-text="hint(item)"></p>
                </div>
            </label>
        </template>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button type="button" x-show="more(group)" x-cloak @click="olderList(group)"
            x-bind:disabled="listBusy[group]"
            class="rounded-xl border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:border-brand-400 hover:text-brand-700 disabled:opacity-50">
            مدل‌های بیشتر
        </button>

        <p x-show="more(group)" x-cloak class="text-xs text-stone-400">
            <span x-text="digits(count(group) - shownCount(group))"></span>
            مدل دیگر هم هست؛ با جستجوی نام زودتر پیدا می‌شود.
        </p>

        <p x-show="q[group] && ! listBusy[group] && shownCount(group) === 0" x-cloak
            class="text-xs font-medium text-amber-700">
            هیچ مدلی با این نام پیدا نشد.
        </p>
    </div>

    @error($group)
        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>
