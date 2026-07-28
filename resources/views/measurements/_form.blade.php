@use('App\Support\Jalali')
@use('App\Support\Measurements')

@php
    /** راهنمای کوتاه «چطور اندازه بگیرم؟» برای شش عدد ضروری. */
    $hints = [
        'height' => 'پشت به دیوار، بدون کفش. از فرق سر تا کف پا.',
        'bust' => 'متر را افقی دور برجسته‌ترین جای سینه بگذارید؛ نه شل و نه محکم.',
        'waist' => 'باریک‌ترین جای کمر، معمولاً کمی بالای ناف. نفس را نگه ندارید.',
        'hip' => 'پرترین قسمت باسن، حدود ۲۰ سانتی‌متر پایین‌تر از کمر.',
        'shoulder_width' => 'از پشت، از استخوان یک سرشانه تا استخوان سرشانه دیگر.',
        'arm_length' => 'از استخوان سرشانه تا مچ، با آرنج کمی خم و دست آزاد.',
    ];

    $essentials = Measurements::essential();
    $optional = Measurements::optional();

    $current = [];

    foreach (Measurements::FIELDS as $key => $field) {
        $current[$key] = (string) old('values.'.$key, $values[$key] ?? '');
    }

    $config = [
        'fields' => collect(Measurements::FIELDS)
            ->map(fn ($field) => ['label' => $field['label'], 'min' => $field['min'], 'max' => $field['max']])
            ->all(),
        'essentials' => array_keys($essentials),
        'sizeTable' => Measurements::SIZE_TABLE,
        'values' => $current,
        'previous' => $previous?->values ?: null,
    ];

    $inputClass =
        'w-full rounded-xl border bg-white px-3.5 py-2.5 pe-16 text-center text-lg font-bold tabular-nums shadow-sm transition focus:ring-2 focus:ring-brand-200';
@endphp

<div x-data="measurementForm(@js($config))" class="space-y-5">
    {{-- شش عدد ضروری --}}
    <x-card title="شش عددی که همیشه لازم است" icon="ruler"
        subtitle="فقط همین شش عدد را بگیرید؛ باقی اندازه‌ها خودکار تخمین زده می‌شود.">
        <x-slot:actions>
            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600">
                <span x-text="essentialsLabel">{{ Jalali::digits(0).' از '.Jalali::digits(count($essentials)) }}</span>
                عدد
            </span>
        </x-slot:actions>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($essentials as $key => $field)
                <div x-data="{ hint: false }">
                    <label for="m-{{ $key }}" class="block text-sm font-semibold text-stone-800">
                        {{ $field['label'] }}
                    </label>

                    <div class="relative mt-1.5">
                        <input type="text" inputmode="decimal" dir="ltr" id="m-{{ $key }}"
                            name="values[{{ $key }}]" value="{{ $current[$key] }}"
                            x-model="values.{{ $key }}" @input="normalize('{{ $key }}')" autocomplete="off"
                            placeholder="{{ Jalali::digits((string) ($field['default'] ?? '')) }}"
                            class="{{ $inputClass }} {{ $errors->has('values.'.$key) ? 'border-rose-400 focus:border-rose-500' : 'border-stone-300 focus:border-brand-500' }}">
                        <span
                            class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-stone-400">
                            سانتی‌متر
                        </span>
                    </div>

                    <button type="button" @click="hint = !hint"
                        class="mt-1.5 inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-700">
                        <x-icon name="info" class="h-3.5 w-3.5" />
                        چطور اندازه بگیرم؟
                    </button>

                    <p x-cloak x-show="hint" x-collapse class="mt-1 rounded-lg bg-brand-50 p-2 text-xs text-brand-800">
                        {{ $hints[$key] ?? 'متر را بدون کشیدن روی بدن بگذارید.' }}
                    </p>

                    @error('values.'.$key)
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        {{-- سایز پیشنهادی و هشدارها --}}
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-brand-600">
                    <x-icon name="sparkles" class="h-6 w-6" />
                </span>
                <div>
                    <p class="text-xs text-brand-700">سایز استاندارد پیشنهادی</p>
                    <p class="text-2xl font-black text-brand-800" x-text="suggestedSizeLabel">
                        {{ $values ? Jalali::digits(Measurements::guessSize($values)) : '—' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-stone-200 bg-stone-50 p-4 text-xs text-stone-600">
                <x-icon name="info" class="h-5 w-5 shrink-0 text-stone-400" />
                <p>
                    بقیه اندازه‌ها (دور گردن، حلقه آستین، قد بالاتنه و…) اگر خالی بمانند
                    هنگام ساخت الگو از روی همین شش عدد تخمین زده می‌شود.
                </p>
            </div>
        </div>

        <template x-if="warnings.length">
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">یک بار دیگر نگاه کنید:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    <template x-for="message in warnings" :key="message">
                        <li x-text="message"></li>
                    </template>
                </ul>
            </div>
        </template>

        <p x-cloak x-show="notice" x-transition class="mt-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"
            x-text="notice"></p>
    </x-card>

    {{-- میان‌برها: شروع از سایز استاندارد یا کپی برگ پیشین --}}
    <x-card title="میان‌بُرها" icon="copy" subtitle="اگر وقت اندازه‌گیری ندارید، از یک نقطه شروع آماده استفاده کنید.">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="picked-size" class="block text-sm font-medium text-stone-700">
                    شروع از یک سایز استاندارد
                </label>
                <div class="mt-1.5 flex items-center gap-2">
                    <select id="picked-size" x-model="pickedSize"
                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                        <option value="">انتخاب سایز…</option>
                        @foreach (Measurements::sizes() as $size)
                            <option value="{{ $size }}">سایز {{ Jalali::digits($size) }}</option>
                        @endforeach
                    </select>
                    <x-button type="button" variant="secondary" x-bind:disabled="! pickedSize"
                        @click="fillFromSize()">پر کن</x-button>
                </div>
                <p class="mt-1 text-xs text-stone-500">اعداد جدول استاندارد پر می‌شود و می‌توانید تغییرشان دهید.</p>
            </div>

            <div>
                <p class="block text-sm font-medium text-stone-700">کپی از برگ پیشین</p>
                <div class="mt-1.5 flex items-center gap-2">
                    @if ($previous)
                        <x-button type="button" variant="secondary" icon="copy" @click="copyPrevious()">
                            کپی «{{ $previous->displayTitle() }}»
                        </x-button>
                    @else
                        <p class="text-sm text-stone-400">برگ پیشینی برای این مشتری نیست.</p>
                    @endif

                    <x-button type="button" variant="ghost" icon="refresh" @click="clearAll()">خالی کردن</x-button>
                </div>
                @if ($previous)
                    <p class="mt-1 text-xs text-stone-500">
                        ثبت‌شده در {{ Jalali::date($previous->created_at, true) }}
                    </p>
                @endif
            </div>
        </div>
    </x-card>

    {{-- اندازه‌های تکمیلی --}}
    <x-advanced-section title="اندازه‌های تکمیلی (اختیاری)"
        description="هرکدام را خالی بگذارید، خودکار از روی شش عدد بالا حساب می‌شود."
        :open="collect(array_keys($optional))->contains(fn ($key) => $errors->has('values.'.$key))">
        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($optional as $key => $field)
                <div>
                    <label for="m-{{ $key }}" class="block text-xs font-medium text-stone-600">
                        {{ $field['label'] }}
                    </label>
                    <input type="text" inputmode="decimal" dir="ltr" id="m-{{ $key }}"
                        name="values[{{ $key }}]" value="{{ $current[$key] }}" x-model="values.{{ $key }}"
                        @input="normalize('{{ $key }}')" autocomplete="off"
                        class="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-center text-sm tabular-nums shadow-sm focus:ring-2 focus:ring-brand-200 {{ $errors->has('values.'.$key) ? 'border-rose-400' : 'border-stone-300 focus:border-brand-500' }}">
                    @error('values.'.$key)
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </x-advanced-section>

    {{-- عنوان، سایز پایه و یادداشت --}}
    <x-card title="نام این برگ" icon="book">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="عنوان" name="title" hint="مثلاً «اندازه‌های بهار ۱۴۰۵» — خالی بگذارید هم اشکالی ندارد.">
                <x-input name="title" :value="$set?->title" placeholder="اندازه‌های تازه" />
            </x-field>

            <x-field label="سایز پایه" name="base_size" hint="اگر خالی بماند از دور سینه حساب می‌شود.">
                <x-select name="base_size" :selected="$set?->base_size" placeholder="خودکار"
                    :options="collect(Measurements::sizes())->mapWithKeys(fn ($size) => [$size => 'سایز '.Jalali::digits($size)])->all()" />
            </x-field>

            <x-field label="یادداشت" name="notes" class="sm:col-span-2">
                <x-textarea name="notes" :value="$set?->notes" rows="2"
                    placeholder="مثلاً: شانه راست کمی افتاده است." />
            </x-field>
        </div>

        <label class="mt-4 flex items-start gap-3 rounded-xl bg-stone-50 p-3">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $set?->is_default ?? true))
                class="mt-0.5 h-4 w-4 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
            <span class="text-sm">
                <span class="font-medium text-stone-800">این اندازه، اندازه اصلی این مشتری باشد</span>
                <span class="block text-xs text-stone-500">
                    برای ساخت الگو و پروژه‌های تازه از همین برگ استفاده می‌شود.
                </span>
            </span>
        </label>
    </x-card>
</div>
