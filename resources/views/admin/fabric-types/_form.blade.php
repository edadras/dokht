@php
    /** فرم مشترک ساخت و ویرایش نوع پارچه. */
    $fibers = old('fibers', $type->fiber_composition ?: [['name' => '', 'percent' => 100]]);
    $groups = collect(\App\Support\FabricProfile::SCHEMA)->groupBy('group');
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card title="شناسه پارچه" icon="palette" subtitle="همان چیزی که خیاط در بانک پارچه می‌بیند.">
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
                <x-field label="شناسه" name="code" required hint="لاتین و بی‌فاصله؛ مثل cotton-poplin">
                    <x-input name="code" :value="$type->code" required dir="ltr" class="text-left" />
                </x-field>

                <x-field label="دسته" name="category" required>
                    <x-select name="category" :options="\App\Models\FabricType::CATEGORIES" :selected="$type->category ?? 'natural'" required />
                </x-field>

                <x-field label="خانواده بافت" name="family">
                    <x-select name="family" :options="\App\Models\FabricType::FAMILIES" :selected="$type->family" placeholder="نامشخص" />
                </x-field>
            </div>

            {{-- ترکیب الیاف --}}
            <div x-data="{ rows: @js(array_values($fibers)) }" class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-stone-800">ترکیب الیاف</p>
                        <p class="text-xs text-stone-500">مجموع درصدها باید حدود ۱۰۰ باشد.</p>
                    </div>
                    <x-button type="button" size="sm" variant="secondary" icon="plus"
                        x-on:click="rows.push({ name: '', percent: 0 })">افزودن</x-button>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="text" x-model="row.name" :name="`fibers[${index}][name]`"
                                placeholder="نام الیاف مثل پنبه"
                                class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                            <input type="number" step="0.1" min="0" max="100" x-model="row.percent"
                                :name="`fibers[${index}][percent]`"
                                class="w-24 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                            <span class="text-xs text-stone-400">٪</span>
                            <button type="button" x-on:click="rows.splice(index, 1)"
                                class="rounded-lg p-2 text-stone-400 hover:bg-white hover:text-rose-600">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </template>
                </div>

                @error('fibers')
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <x-field label="توضیح کوتاه" name="description">
                <x-textarea name="description" :value="$type->description" rows="3"
                    placeholder="این پارچه برای چه لباسی خوب است و چه حال‌وهوایی دارد؟" />
            </x-field>
        </div>
    </x-card>

    <x-advanced-section title="شناسنامه فیزیکی پارچه"
        description="این اعداد به موتور قواعد، شبیه‌سازی و چیدمان برش خورده می‌شوند. مقدارهای پیش‌فرض کار می‌کنند.">
        <div class="space-y-6">
            @foreach (\App\Support\FabricProfile::GROUPS as $groupKey => $groupLabel)
                @php $fields = $groups->get($groupKey, collect()); @endphp

                @if ($fields->isNotEmpty())
                    <div>
                        <p class="mb-3 text-sm font-bold text-stone-800">{{ $groupLabel }}</p>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (\App\Support\FabricProfile::SCHEMA as $key => $definition)
                                @continue(($definition['group'] ?? null) !== $groupKey)

                                @php
                                    $name = 'profile.'.$key;
                                    $value = old($name, $profile[$key] ?? $definition['default']);
                                @endphp

                                @if ($definition['type'] === 'ratio')
                                    <div x-data="{ value: {{ (float) $value }} }" class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium text-stone-700"
                                                for="profile-{{ $key }}">{{ $definition['label'] }}</label>
                                            <span class="text-xs font-semibold text-brand-700"
                                                x-text="Number(value).toFixed(2)"></span>
                                        </div>
                                        <input type="range" min="0" max="1" step="0.05" x-model="value"
                                            id="profile-{{ $key }}" name="profile[{{ $key }}]"
                                            class="w-full accent-brand-600">
                                        @if (! empty($definition['hint']))
                                            <p class="text-xs text-stone-500">{{ $definition['hint'] }}</p>
                                        @endif
                                    </div>
                                @elseif ($definition['type'] === 'bool')
                                    <label
                                        class="flex items-start gap-3 rounded-xl border border-stone-200 bg-white p-3">
                                        <input type="checkbox" name="profile[{{ $key }}]" value="1"
                                            @checked((bool) $value)
                                            class="mt-0.5 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                                        <span class="min-w-0">
                                            <span
                                                class="block text-sm font-medium text-stone-700">{{ $definition['label'] }}</span>
                                            @if (! empty($definition['hint']))
                                                <span
                                                    class="block text-xs text-stone-500">{{ $definition['hint'] }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @elseif ($definition['type'] === 'option')
                                    <x-field :label="$definition['label']" :name="$name" :hint="$definition['hint'] ?? null">
                                        <select name="profile[{{ $key }}]"
                                            class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                            @foreach ($definition['options'] as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>
                                                    {{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                @else
                                    <x-field :label="$definition['label']" :name="$name" :suffix="$definition['unit'] ?: null"
                                        :hint="$definition['hint'] ?? null">
                                        <input type="number" name="profile[{{ $key }}]" value="{{ $value }}"
                                            step="{{ ($definition['max'] ?? 100) <= 10 ? '0.05' : '1' }}"
                                            min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? 1000 }}"
                                            class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-24 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                    </x-field>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <div class="grid gap-4 border-t border-stone-100 pt-5 sm:grid-cols-2">
                <x-field label="نکته‌های نگهداری" name="care" hint="هر خط یک نکته؛ مثل «شست‌وشو با آب سرد».">
                    <x-textarea name="care" rows="4" :value="implode(PHP_EOL, (array) ($type->care ?? []))" />
                </x-field>

                <x-field label="نکته‌های دوخت" name="sewing" hint="هر خط یک نکته؛ مثل «سوزن ۷۰، کوک ریز».">
                    <x-textarea name="sewing" rows="4" :value="implode(PHP_EOL, (array) ($type->sewing ?? []))" />
                </x-field>
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
                <span class="text-sm font-medium text-stone-700">فعال (در بانک پارچه دیده شود)</span>
            </label>
        </div>
    </x-card>

    <div class="flex flex-wrap justify-end gap-2">
        <x-button href="{{ route('admin.fabric-types.index') }}" variant="secondary">انصراف</x-button>
        <x-button type="submit" icon="check">{{ $submit }}</x-button>
    </div>
</form>
