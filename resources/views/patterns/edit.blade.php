<x-app-layout :title="'تنظیم‌های '.$pattern->name">
    <x-page-header title="تنظیم‌های الگو" :subtitle="$pattern->name" :back="route('patterns.show', $pattern)" />

    <form method="POST" action="{{ route('patterns.update', $pattern) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card title="اطلاعات الگو" icon="scissors">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نام الگو" name="name" required>
                    <x-input name="name" :value="$pattern->name" required />
                </x-field>

                <x-field label="سایز مبنا" name="base_size">
                    <x-select name="base_size"
                        :options="collect($sizes)->mapWithKeys(fn ($size) => [$size => 'سایز '.\App\Support\Jalali::digits($size)])"
                        :selected="$pattern->base_size" />
                </x-field>
            </div>

            <x-field label="یادداشت" name="notes" class="mt-4">
                <x-textarea name="notes" :value="$pattern->notes" rows="2" />
            </x-field>

            @if ($pattern->template)
                <label class="mt-4 flex items-center gap-2 rounded-xl bg-brand-50 p-3 text-sm text-brand-800">
                    <input type="checkbox" name="regenerate" value="1" checked
                        class="rounded border-brand-300 text-brand-600 focus:ring-brand-400">
                    الگو با تنظیم‌های تازه از نو ساخته شود (نسخه فعلی پیش از آن ذخیره می‌شود).
                </label>
            @else
                <x-alert type="warning" class="mt-4">
                    این الگو به مدل پایه‌ای وصل نیست، پس فقط جای دوخت و اطلاعات آن به‌روز می‌شود.
                </x-alert>
            @endif
        </x-card>

        <x-card title="آزادی لباس" icon="ruler" subtitle="مقدار اضافه روی اندازه بدن؛ منفی برای پارچه کشی.">
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach (['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو'] as $key => $label)
                    <x-field :label="$label" :name="'ease.'.$key">
                        <x-input type="number" step="0.5" :name="'ease['.$key.']'"
                            :value="data_get($pattern->ease, $key)" suffix="سانتی‌متر" />
                    </x-field>
                @endforeach
            </div>
        </x-card>

        <x-advanced-section title="جای دوخت و پارامترهای مدل" description="پیش‌فرض‌ها برای بیشتر کارها مناسب‌اند."
            :open="true">
            <div class="space-y-6">
                <div>
                    <h3 class="mb-3 text-sm font-bold text-stone-700">جای دوخت هر لبه</h3>
                    <div class="grid gap-4 sm:grid-cols-4">
                        @foreach ($seamTags as $tag => $label)
                            <x-field :label="$label" :name="'seam_allowances.'.$tag">
                                <x-input type="number" step="0.1" min="0" max="10" :name="'seam_allowances['.$tag.']'"
                                    :value="data_get($pattern->seam_allowances, $tag)" suffix="سانتی‌متر" />
                            </x-field>
                        @endforeach
                    </div>
                </div>

                @if (! empty($schema))
                    <div>
                        <h3 class="mb-3 text-sm font-bold text-stone-700">پارامترهای مدل «{{ $pattern->template->name_fa }}»</h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            @foreach ($schema as $key => $field)
                                @php
                                    $value = data_get($pattern->params, $key, $field['default'] ?? null);
                                    $isToggle = ($field['type'] ?? 'number') === 'toggle';
                                @endphp

                                <x-field :label="$field['label'] ?? $key" :hint="$field['hint'] ?? null">
                                    @if ($isToggle)
                                        <select name="params[{{ $key }}]"
                                            class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                            <option value="1" @selected($value)>دارد</option>
                                            <option value="0" @selected(! $value)>ندارد</option>
                                        </select>
                                    @else
                                        <x-input type="number" :name="'params['.$key.']'" :value="$value"
                                            :step="$field['step'] ?? 0.5" :min="$field['min'] ?? null"
                                            :max="$field['max'] ?? null" :suffix="$field['unit'] ?? null" />
                                    @endif
                                </x-field>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-advanced-section>

        <div class="flex items-center justify-end gap-3">
            <x-button href="{{ route('patterns.show', $pattern) }}" variant="ghost">بازگشت</x-button>
            <x-button type="submit" icon="check">ذخیره و ساخت دوباره</x-button>
        </div>
    </form>
</x-app-layout>
