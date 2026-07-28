@php
    /** فرم مشترک ساخت و ویرایش نوع لباس. */
    $selectedParts = old('parts', $type->parts ?? []);
    $selectedMeasurements = old('required_measurements', $type->required_measurements ?? []);
    $ease = old('ease', $type->ease());
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card title="شناسه لباس" icon="shirt">
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نام فارسی" name="name_fa" required>
                    <x-input name="name_fa" :value="$type->name_fa" required autofocus />
                </x-field>

                <x-field label="نام لاتین" name="name_en">
                    <x-input name="name_en" :value="$type->name_en" dir="ltr" class="text-left" />
                </x-field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="شناسه" name="code" required hint="لاتین و بی‌فاصله؛ مثل shirt-basic">
                    <x-input name="code" :value="$type->code" required dir="ltr" class="text-left" />
                </x-field>

                <x-field label="دسته" name="category" required>
                    <x-select name="category" :options="\App\Models\GarmentType::CATEGORIES" :selected="$type->category" required
                        placeholder="انتخاب کنید" />
                </x-field>

                <x-field label="آیکون" name="icon" hint="نام آیکون؛ مثل shirt">
                    <x-input name="icon" :value="$type->icon" dir="ltr" class="text-left" />
                </x-field>
            </div>
        </div>
    </x-card>

    <x-card title="اجزای این لباس" icon="layers" subtitle="کدام قطعه‌ها باید ساخته شوند؟">
        <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach (\App\Models\GarmentType::PART_LABELS as $part => $label)
                <label class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                    <input type="checkbox" name="parts[]" value="{{ $part }}"
                        @checked(in_array($part, (array) $selectedParts, true))
                        class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                    <span class="text-stone-700">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        @error('parts')
            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
        @enderror
    </x-card>

    <x-card title="آزادی پیش‌فرض" icon="ruler" subtitle="چند سانتی‌متر روی اندازه بدن اضافه شود تا لباس راحت باشد.">
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach (\App\Http\Controllers\Admin\GarmentTypeController::EASE_FIELDS as $key => $label)
                <x-field :label="$label" :name="'ease.'.$key" suffix="سانتی‌متر">
                    <input type="number" step="0.5" min="-10" max="60" name="ease[{{ $key }}]"
                        value="{{ $ease[$key] ?? '' }}"
                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-20 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                </x-field>
            @endforeach
        </div>
    </x-card>

    <x-advanced-section title="اندازه‌های لازم و ترجیح پارچه"
        description="اگر مطمئن نیستید، دست نزنید؛ مقدارهای پیش‌فرض برای بیشتر لباس‌ها درست است.">
        <div class="space-y-6">
            <div>
                <p class="mb-1 text-sm font-bold text-stone-800">اندازه‌هایی که برای این لباس لازم است</p>
                <p class="mb-3 text-xs text-stone-500">
                    اندازه‌های خالی از روی اندازه‌های اصلی تخمین زده می‌شوند، پس فقط موارد واقعاً لازم را علامت بزنید.
                </p>

                <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach (\App\Support\Measurements::FIELDS as $key => $definition)
                        <label
                            class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                            <input type="checkbox" name="required_measurements[]" value="{{ $key }}"
                                @checked(in_array($key, (array) $selectedMeasurements, true))
                                class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                            <span class="text-stone-700">{{ $definition['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-stone-100 pt-5">
                <p class="mb-1 text-sm font-bold text-stone-800">پارچه مناسب این لباس</p>
                <p class="mb-3 text-xs text-stone-500">
                    از این اعداد برای نمره‌دادن به سازگاری پارچه با مدل استفاده می‌شود.
                </p>

                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="لختی آرمانی" name="preferences.drape.ideal" hint="۰ بسیار ایستا تا ۱ بسیار لخت">
                            <input type="number" step="0.05" min="0" max="1" name="preferences[drape][ideal]"
                                value="{{ old('preferences.drape.ideal', $preferences['drape']['ideal'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>

                        <x-field label="رواداری لختی" name="preferences.drape.tolerance"
                            hint="چقدر می‌توان از مقدار آرمانی فاصله گرفت.">
                            <input type="number" step="0.05" min="0" max="1" name="preferences[drape][tolerance]"
                                value="{{ old('preferences.drape.tolerance', $preferences['drape']['tolerance'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="سفتی آرمانی" name="preferences.stiffness.ideal">
                            <input type="number" step="0.05" min="0" max="1" name="preferences[stiffness][ideal]"
                                value="{{ old('preferences.stiffness.ideal', $preferences['stiffness']['ideal'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>

                        <x-field label="رواداری سفتی" name="preferences.stiffness.tolerance">
                            <input type="number" step="0.05" min="0" max="1"
                                name="preferences[stiffness][tolerance]"
                                value="{{ old('preferences.stiffness.tolerance', $preferences['stiffness']['tolerance'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="کمینه وزن پارچه" name="preferences.weight_gsm.min" suffix="گرم/م²">
                            <input type="number" step="5" min="10" max="900" name="preferences[weight_gsm][min]"
                                value="{{ old('preferences.weight_gsm.min', $preferences['weight_gsm']['min'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-20 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>

                        <x-field label="بیشینه وزن پارچه" name="preferences.weight_gsm.max" suffix="گرم/م²">
                            <input type="number" step="5" min="10" max="900" name="preferences[weight_gsm][max]"
                                value="{{ old('preferences.weight_gsm.max', $preferences['weight_gsm']['max'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-20 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>

                        <x-field label="بیشینه شفافیت" name="preferences.transparency.max" hint="۰ کاملاً پوشاننده">
                            <input type="number" step="0.05" min="0" max="1" name="preferences[transparency][max]"
                                value="{{ old('preferences.transparency.max', $preferences['transparency']['max'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="کمینه کشسانی عرض" name="preferences.stretch_weft.min" suffix="٪">
                            <input type="number" step="1" min="0" max="200" name="preferences[stretch_weft][min]"
                                value="{{ old('preferences.stretch_weft.min', $preferences['stretch_weft']['min'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-16 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>

                        <x-field label="بیشینه کشسانی عرض" name="preferences.stretch_weft.max" suffix="٪">
                            <input type="number" step="1" min="0" max="200" name="preferences[stretch_weft][max]"
                                value="{{ old('preferences.stretch_weft.max', $preferences['stretch_weft']['max'] ?? '') }}"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-16 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </x-field>
                    </div>
                </div>
            </div>
        </div>
    </x-advanced-section>

    <x-card title="نمایش در فهرست" icon="settings">
        <div class="grid items-end gap-4 sm:grid-cols-2">
            <x-field label="ترتیب نمایش" name="sort" hint="عدد کمتر بالاتر می‌آید.">
                <x-input type="number" name="sort" :value="$type->sort ?? 0" min="0" max="9999" />
            </x-field>

            <label class="flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active ?? true))
                    class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                <span class="text-sm font-medium text-stone-700">فعال (در فهرست مدل‌ها دیده شود)</span>
            </label>
        </div>
    </x-card>

    <div class="flex flex-wrap justify-end gap-2">
        <x-button href="{{ route('admin.garment-types.index') }}" variant="secondary">انصراف</x-button>
        <x-button type="submit" icon="check">{{ $submit }}</x-button>
    </div>
</form>
