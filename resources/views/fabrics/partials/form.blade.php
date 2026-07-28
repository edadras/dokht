{{--
    فرم پارچه.
    فقط شش فیلد در دید کاربر است؛ همه چیز فنی داخل بخش پیشرفته پنهان شده و از روی
    پارچه پایه پیش‌پر می‌شود.
--}}
@php
    $isEdit = $fabric->exists;
    $config = [
        'schema' => $schema,
        'base' => $baseProfile,
        'profile' => array_merge($baseProfile, old('profile', $overrides)),
        'types' => $typeProfiles,
        'typeId' => old('fabric_type_id', $fabric->fabric_type_id),
    ];
@endphp

<form method="POST" action="{{ $isEdit ? route('fabrics.update', $fabric) : route('fabrics.store') }}"
    enctype="multipart/form-data" class="space-y-6" x-data="fabricProfile(@js($config))">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card title="مشخصات پارچه" subtitle="همین چند مورد کافی است؛ بقیه مشخصات از پارچه پایه خوانده می‌شود." icon="layers">
        <div class="space-y-4">
            <x-field label="پارچه پایه" name="fabric_type_id" required
                hint="از بانک پارچه انتخاب کنید تا شناسنامه فیزیکی خودکار پر شود.">
                <select name="fabric_type_id" id="fabric_type_id" required @change="applyType($event.target.value)"
                    class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <option value="">انتخاب کنید…</option>
                    @foreach ($typeGroups as $groupLabel => $options)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}"
                                    @selected((string) old('fabric_type_id', $fabric->fabric_type_id) === (string) $id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نام پارچه" name="name" hint="اگر خالی بگذارید نام پارچه پایه گذاشته می‌شود.">
                    <x-input name="name" :value="$fabric->name" placeholder="مثلاً کرپ مشکی مجلسی" />
                </x-field>

                <x-field label="رنگ" name="color">
                    <div class="flex gap-2">
                        <x-input name="color" :value="$fabric->color" placeholder="مثلاً سرمه‌ای" class="flex-1" />
                        <input type="color" name="color_hex" value="{{ old('color_hex', $fabric->color_hex ?: '#8b5e3c') }}"
                            class="h-11 w-14 shrink-0 cursor-pointer rounded-xl border border-stone-300 bg-white p-1"
                            aria-label="انتخاب رنگ">
                    </div>
                </x-field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="عرض پارچه" name="width_cm" suffix="سانتی‌متر">
                    <x-input type="number" name="width_cm" :value="$fabric->width_cm" step="1" min="30" max="400"
                        placeholder="۱۴۰" />
                </x-field>

                <x-field label="قیمت هر متر" name="price_per_meter" suffix="تومان">
                    <x-input type="number" name="price_per_meter" :value="$fabric->price_per_meter ? (int) $fabric->price_per_meter : null"
                        step="1000" min="0" />
                </x-field>

                <x-field label="موجودی" name="stock_meters" suffix="متر">
                    <x-input type="number" name="stock_meters" :value="$fabric->exists ? $fabric->stock_meters : null" step="0.1"
                        min="0" placeholder="۰" />
                </x-field>
            </div>
        </div>
    </x-card>

    <x-advanced-section title="مشخصات پیشرفته پارچه"
        description="طرح روی پارچه، تصاویر بافت و ویژگی‌های فیزیکی — همه با مقدار پیش‌فرض پر شده‌اند.">
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="طرح روی پارچه" name="surface_pattern">
                    <x-select name="surface_pattern" :options="$patternOptions" :selected="$fabric->surface_pattern ?: 'plain'" />
                </x-field>

                <x-field label="اندازه تکرار طرح" name="pattern_repeat_cm" suffix="سانتی‌متر"
                    hint="برای راه‌راه و چهارخانه لازم است.">
                    <x-input type="number" name="pattern_repeat_cm" :value="$fabric->pattern_repeat_cm" step="0.5" min="0" />
                </x-field>

                <x-field label="وزن این تُوپ پارچه" name="gsm" suffix="گرم بر متر مربع"
                    hint="خالی بگذارید تا از پارچه پایه خوانده شود.">
                    <x-input type="number" name="gsm" :value="$fabric->gsm" step="5" min="10" max="900" />
                </x-field>
            </div>

            <x-field label="یادداشت" name="notes">
                <x-textarea name="notes" :value="$fabric->notes" rows="2"
                    placeholder="محل خرید، شماره تُوپ، نکته‌ای که می‌خواهید یادتان بماند…" />
            </x-field>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="تصویر بافت" name="texture" hint="برای نمای سه‌بعدی">
                    <x-input type="file" name="texture" accept="image/*" class="py-2" />
                </x-field>

                <x-field label="نقشه برجستگی" name="normal_map">
                    <x-input type="file" name="normal_map" accept="image/*" class="py-2" />
                </x-field>

                <x-field label="نقشه زبری" name="roughness_map">
                    <x-input type="file" name="roughness_map" accept="image/*" class="py-2" />
                </x-field>
            </div>

            {{-- پیش‌نمای زنده ریزش پارچه --}}
            <div class="flex flex-wrap items-center gap-5 rounded-2xl border border-stone-200 bg-stone-50 p-4">
                <svg viewBox="0 0 120 128" class="h-32 w-28 shrink-0" aria-hidden="true">
                    <path :d="drapePath()" fill="#e7d3c1" stroke="#a97b52" stroke-width="1.5" stroke-linejoin="round" />
                    <path :d="foldPath()" fill="none" stroke="#a97b52" stroke-width="0.9" stroke-opacity="0.5" />
                </svg>

                <div class="min-w-40 flex-1 space-y-1 text-sm">
                    <p class="font-bold text-stone-800" x-text="typeName"></p>
                    <p class="text-stone-600">ریزش: <span class="font-semibold" x-text="drapeLabel"></span></p>
                    <p class="text-stone-600">وزن: <span class="font-semibold" x-text="weightLabel"></span></p>
                    <p class="text-xs text-stone-500">
                        <span x-text="overriddenCount === 0 ? 'همه مقادیر برابر پارچه پایه است.' : 'شما ' + overriddenCount + ' مورد را دستی تغییر داده‌اید.'"></span>
                    </p>
                    <button type="button" @click="resetAll()" x-show="overriddenCount > 0"
                        class="text-xs font-semibold text-brand-600 hover:underline">بازگشت همه به پارچه پایه</button>
                </div>
            </div>

            @foreach ($groups as $groupKey => $groupLabel)
                <div>
                    <p class="mb-3 text-sm font-bold text-stone-700">{{ $groupLabel }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($schema as $key => $definition)
                            @continue($definition['group'] !== $groupKey)

                            <div class="rounded-xl border border-stone-200 bg-white p-3">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <label for="profile-{{ $key }}" class="text-sm font-medium text-stone-700">
                                        {{ $definition['label'] }}
                                    </label>
                                    <span class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-brand-700"
                                            x-text="valueLabel(@js($key))"></span>
                                        <button type="button" @click="reset(@js($key))"
                                            x-show="isOverridden(@js($key))" class="text-stone-400 hover:text-rose-500"
                                            title="بازگشت به پارچه پایه">
                                            <x-icon name="refresh" class="h-4 w-4" />
                                        </button>
                                    </span>
                                </div>

                                @if ($definition['type'] === 'bool')
                                    <input type="hidden" name="profile[{{ $key }}]" value="0">
                                    <label class="flex items-center gap-2 text-sm text-stone-600">
                                        <input type="checkbox" id="profile-{{ $key }}"
                                            name="profile[{{ $key }}]" value="1"
                                            x-model="profile.{{ $key }}"
                                            class="h-4 w-4 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                                        بله
                                    </label>
                                @elseif ($definition['type'] === 'option')
                                    <select id="profile-{{ $key }}" name="profile[{{ $key }}]"
                                        x-model="profile.{{ $key }}"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                        @foreach ($definition['options'] ?? [] as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($definition['type'] === 'number')
                                    <input type="number" id="profile-{{ $key }}"
                                        name="profile[{{ $key }}]" x-model.number="profile.{{ $key }}"
                                        min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? 1000 }}"
                                        step="{{ ($definition['max'] ?? 1000) > 10 ? 1 : 0.05 }}"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                @else
                                    <input type="range" id="profile-{{ $key }}"
                                        name="profile[{{ $key }}]" x-model.number="profile.{{ $key }}"
                                        min="{{ $definition['min'] ?? 0 }}"
                                        max="{{ $definition['max'] ?? ($definition['type'] === 'ratio' ? 1 : 100) }}"
                                        step="{{ $definition['type'] === 'ratio' ? 0.01 : 1 }}"
                                        class="w-full accent-brand-600">
                                @endif

                                @if (! empty($definition['hint']))
                                    <p class="mt-1.5 text-xs text-stone-500">{{ $definition['hint'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $fabric->exists ? $fabric->is_active : true))
                    class="h-4 w-4 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                این پارچه در انبار فعال است
            </label>
        </div>
    </x-advanced-section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-button href="{{ $isEdit ? route('fabrics.show', $fabric) : route('fabrics.index') }}" variant="ghost">
            بازگشت
        </x-button>

        <x-button type="submit" size="lg" icon="check">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت پارچه' }}</x-button>
    </div>
</form>
