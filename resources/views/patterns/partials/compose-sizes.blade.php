{{--
    گام سوم: اندازه‌های خودِ لباس.

    این‌ها همان پارامترهای مدل‌اند که تا حالا در «تنظیمات حرفه‌ای» پنهان بودند —
    قد لباس، قد آستین، بلندی دامن، گشادی دم، بلندی یقه و مچ. ولی این‌ها چیزی
    نیستند که خیاط «اگر لازم شد» بازشان کند؛ همان اول می‌خواهد ببیندشان و دست
    بزند. پس آمدند جلو و بقیهٔ تنظیم‌های ریز سرِ جای خودشان ماندند.

    هیچ‌کدام این‌جا دستی نوشته نشده: هر مدلی که انتخاب شود، اندازه‌های خودش را
    می‌آورد و هر عددی که عوض شود، پیش‌نمایش و نمای دوخت همان لحظه به‌روز می‌شود.
--}}
<x-card title="۳. اندازه‌های لباس" icon="ruler"
    subtitle="قد لباس، قد آستین و بقیهٔ اندازه‌ها — همه سانتی‌متر و همه قابل تغییر.">
    <x-slot:actions>
        <span class="text-xs text-stone-500">با هر تغییر، تصویر بالا هم عوض می‌شود</span>
    </x-slot:actions>

    <div class="space-y-4">
        <template x-for="role in activeRoles()" :key="'size-' + role.role">
            <div x-show="sizeFields(role).length" class="space-y-3 rounded-2xl border border-stone-200 p-4">
                <p class="text-xs font-semibold text-stone-600">
                    <span x-text="role.title"></span> — <span class="text-stone-400" x-text="role.label"></span>
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="field in sizeFields(role)" :key="'sz-' + role.role + '-' + field.key">
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-stone-700" x-text="field.label"></label>

                            <div class="flex items-center gap-2">
                                <input type="number" :name="'params[' + role.role + '][' + field.key + ']'"
                                    x-model="params[role.role][field.key]" :step="field.step" :min="field.min"
                                    :max="field.max"
                                    class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                <span class="shrink-0 text-xs text-stone-400">سانتی‌متر</span>
                            </div>

                            <p class="text-xs text-stone-400"
                                x-show="field.min !== null && field.max !== null"
                                x-text="'از ' + digits(field.min) + ' تا ' + digits(field.max)"></p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <p class="text-sm text-stone-500" x-show="! anySizeFields()" x-cloak>
            مدل‌هایی که انتخاب کرده‌اید اندازهٔ تنظیم‌شدنی ندارند.
        </p>
    </div>
</x-card>
