{{--
    لباسِ دوخته‌شده روی مانکن.

    زیرِ همان چهار نمای دوبعدی می‌نشیند و از همان اعداد ساخته می‌شود — پس اگر
    آن بالا لباس گشاد است، این‌جا هم گشاد است. مانکن از اندازه‌های همین مشتری
    است، پس فاصلهٔ پارچه از پوست همان آزادیِ واقعیِ الگوست.
--}}
<div x-data="garmentSolid({ payload: @js($solid) })" x-init="boot()" @destroy="destroy()">
    <div class="relative overflow-hidden rounded-2xl border border-stone-200 bg-gradient-to-b from-stone-50 to-stone-100">
        <canvas x-ref="canvas" class="block h-[26rem] w-full"
            aria-label="نمای سه‌بعدی لباس روی مانکن"></canvas>

        <div x-show="! ready && ! failed" x-cloak
            class="absolute inset-0 flex items-center justify-center text-sm text-stone-500">
            در حال نشاندن لباس روی مانکن…
        </div>

        <div x-show="failed" x-cloak
            class="absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-stone-500">
            <span x-text="message"></span>
        </div>

        <button type="button" x-show="ready" x-cloak @click="toggleSpin()"
            class="absolute bottom-3 end-3 rounded-xl border border-stone-300 bg-white/90 px-3 py-1.5 text-xs font-semibold text-stone-600 shadow-sm transition hover:border-brand-400 hover:text-brand-700">
            <span x-text="spin ? 'توقف چرخش' : 'چرخش'"></span>
        </button>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2" x-show="fabrics.length" x-cloak>
        <span class="text-xs font-semibold text-stone-600">پارچه:</span>
        <template x-for="swatch in fabrics" :key="swatch.id">
            <button type="button" @click="wear(swatch)"
                :class="chosen === swatch.id ? 'border-brand-500 ring-2 ring-brand-100' : 'border-stone-300'"
                class="flex items-center gap-2 rounded-xl border-2 bg-white px-2.5 py-1.5 text-xs font-medium text-stone-700 transition hover:border-brand-400">
                <span class="h-4 w-4 rounded-full border border-stone-300" :style="'background:' + swatch.color"></span>
                <span x-text="swatch.name"></span>
            </button>
        </template>
    </div>

    <p class="mt-3 text-xs leading-6 text-stone-500">
        این تصویر شبیه‌سازی پارچه نیست؛ همان لباسی است که بالا از چهار طرف دیدید،
        این بار دور بدن. پهنا و بلندی هر ارتفاعش از خودِ الگو اندازه گرفته شده و
        مانکن از اندازه‌های همین مشتری ساخته شده، پس فاصلهٔ پارچه از پوست همان
        آزادیِ واقعیِ الگوست. رنگ و جنس از پارچهٔ انتخاب‌شده می‌آید.
    </p>
</div>
