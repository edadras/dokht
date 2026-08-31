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

        <div x-show="ready" x-cloak class="absolute bottom-3 end-3 flex flex-wrap items-center gap-1.5">
            <button type="button" @click="toggleSpin()"
                class="rounded-xl border border-stone-300 bg-white/90 px-3 py-1.5 text-xs font-semibold text-stone-600 shadow-sm transition hover:border-brand-400 hover:text-brand-700">
                <span x-text="spin ? 'توقف چرخش' : 'چرخش'"></span>
            </button>
            <button type="button" @click="recentre()" title="بازگشت به قاب نخست"
                class="rounded-xl border border-stone-300 bg-white/90 px-3 py-1.5 text-xs font-semibold text-stone-600 shadow-sm transition hover:border-brand-400 hover:text-brand-700">
                قاب اول
            </button>
            <button type="button" @click="snapshot()" title="ذخیرهٔ همین نما"
                class="rounded-xl border border-stone-300 bg-white/90 px-3 py-1.5 text-xs font-semibold text-stone-600 shadow-sm transition hover:border-brand-400 hover:text-brand-700">
                عکس
            </button>
        </div>

        <div x-show="sewn" x-cloak class="absolute top-3 start-3 flex flex-col gap-1.5">
            <div class="flex overflow-hidden rounded-xl border border-stone-300 bg-white/90 text-xs font-semibold shadow-sm">
                @foreach ([['fabric', 'پارچه'], ['strain', 'کشش'], ['ease', 'آزادی']] as [$key, $label])
                    <button type="button" @click="setView('{{ $key }}')"
                        :class="view === '{{ $key }}' ? 'bg-brand-600 text-white' : 'text-stone-600 hover:text-brand-700'"
                        class="px-3 py-1.5 transition">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" @click="toggleBody()"
                :class="bare ? 'border-brand-500 text-brand-700' : 'border-stone-300 text-stone-600'"
                class="rounded-xl border bg-white/90 px-3 py-1.5 text-xs font-semibold shadow-sm transition hover:border-brand-400">
                <span x-text="bare ? 'نمایش مانکن' : 'برداشتن مانکن'"></span>
            </button>
        </div>

        {{-- راهنمای رنگِ نقشه‌ها، فقط وقتی نقشه روشن است --}}
        <div x-show="sewn && view !== 'fabric'" x-cloak
            class="absolute bottom-3 start-3 rounded-xl border border-stone-300 bg-white/90 px-3 py-2 text-[11px] leading-5 text-stone-600 shadow-sm">
            <template x-if="view === 'strain'">
                <div>
                    <div class="font-semibold text-stone-700">کشش پارچه</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="h-3 w-6 rounded" style="background:#3399e0"></span><span>چین‌خورده</span>
                        <span class="h-3 w-6 rounded" style="background:#8c8c87"></span><span>اندازه</span>
                        <span class="h-3 w-6 rounded" style="background:#f83122"></span><span>کشیده</span>
                    </div>
                </div>
            </template>
            <template x-if="view === 'ease'">
                <div>
                    <div class="font-semibold text-stone-700">آزادی از تن</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="h-3 w-6 rounded" style="background:#eb594d"></span><span>چسبیده</span>
                        <span class="h-3 w-6 rounded" style="background:#52bf6b"></span><span>۲ تا ۶ سانت</span>
                        <span class="h-3 w-6 rounded" style="background:#3880d9"></span><span>گشاد</span>
                    </div>
                </div>
            </template>
        </div>
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
        برای چرخاندن، روی تصویر بکشید؛ با غلتک یا دو انگشت نزدیک و دور کنید و با
        دکمهٔ راست (یا Shift و کشیدن) قاب را جابه‌جا کنید.
        «کشش» نشان می‌دهد پارچه کجا زیر فشار است و کجا چین می‌خورد، و «آزادی»
        فاصلهٔ پارچه تا تن را رنگ می‌کند.
    </p>

    <p class="mt-3 text-xs leading-6 text-stone-500">
        این تصویر شبیه‌سازی پارچه نیست؛ همان لباسی است که بالا از چهار طرف دیدید،
        این بار دور بدن. پهنا و بلندی هر ارتفاعش از خودِ الگو اندازه گرفته شده و
        مانکن از اندازه‌های همین مشتری ساخته شده، پس فاصلهٔ پارچه از پوست همان
        آزادیِ واقعیِ الگوست. رنگ و جنس از پارچهٔ انتخاب‌شده می‌آید.
    </p>
</div>
