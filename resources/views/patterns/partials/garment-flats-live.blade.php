{{--
    همان چهار نمای «لباس دوخته‌شده»، ولی زنده.

    صفحهٔ الگو نماها را در خودِ HTML دارد چون یک بار ساخته می‌شود؛ این‌جا هر بار
    که انتخاب‌ها عوض می‌شوند از پیش‌نمایش می‌آید، پس همان بسته‌ای که SVG نقشه را
    می‌آورد نماها را هم با خودش می‌آورد و مرورگر فقط جایشان می‌گذارد.
--}}
<div class="space-y-3">
    <template x-if="flats.ok">
        <div>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="(svg, key) in flats.views" :key="key">
                    <figure class="rounded-xl border border-stone-200 bg-stone-50 p-2">
                        <div class="flex h-36 items-center justify-center overflow-hidden [&>svg]:max-h-full [&>svg]:w-auto"
                            x-html="svg"></div>
                        <figcaption class="mt-1 text-center text-[11px] font-semibold text-stone-500"
                            x-text="viewName(key)"></figcaption>
                    </figure>
                </template>
            </div>

            <dl class="mt-3 space-y-1 text-xs">
                <template x-for="(value, label) in flats.measures" :key="label">
                    <div class="flex items-baseline justify-between gap-2 border-b border-dashed border-stone-200 pb-0.5">
                        <dt class="text-stone-500" x-text="label"></dt>
                        <dd class="font-semibold text-stone-800" x-text="digits(value) + ' سانتی‌متر'"></dd>
                    </div>
                </template>
            </dl>
        </div>
    </template>

    <template x-if="! flats.ok && ready">
        <p class="rounded-xl bg-stone-50 px-3 py-2 text-xs text-stone-500"
            x-text="(flats.notes && flats.notes[0]) || 'نمای دوخت برای این ترکیب ساخته نشد.'"></p>
    </template>
</div>
