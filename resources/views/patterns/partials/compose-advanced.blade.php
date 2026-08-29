{{--
    همه چیزِ حرفه‌ای این‌جاست: آزادی لباس، دوخت کمر، جای دوخت، پارامترهای هر بلوک و
    پارامترهای هر سبک انتخاب‌شده. همه پیش‌فرض کارآمد دارند، پس دست‌نزدن به این بخش
    هم یک لباس دوختنی می‌دهد.

    فرم پارامترها از روی «توضیح پارامترها»ی خودِ مدل و سبک ساخته می‌شود (نه فهرست
    دستی)، و فقط برای چیزی که همین حالا انتخاب شده در صفحه می‌نشیند.
--}}
<x-advanced-section title="تنظیمات حرفه‌ای"
    description="آزادی لباس، چین کمر، جای دوخت و پارامترهای هر مدل و سبک — همه پیش‌فرض مناسب دارند.">
    <div class="space-y-6">
        <div>
            <h3 class="mb-3 text-sm font-bold text-stone-700">آزادی لباس (سانتی‌متر)</h3>
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach (['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو'] as $key => $label)
                    <x-field :label="$label" :name="'ease.'.$key">
                        <x-input type="number" step="0.5" :name="'ease['.$key.']'" :value="old('ease.'.$key)"
                            placeholder="پیش‌فرض" />
                    </x-field>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-stone-500">مقدار منفی برای پارچه‌های کشی مجاز است.</p>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-bold text-stone-700">دوخت کمر</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="چین کمر پایین‌تنه" name="gather"
                    hint="پایین‌تنه این‌قدر گشادتر بریده و روی کمر چین داده می‌شود.">
                    <x-input type="number" step="1" min="0" max="40" name="gather" :value="old('gather')"
                        placeholder="۰" />
                </x-field>

                <x-field label="اگر کمرها هم‌اندازه نبودند" name="waist_join">
                    <x-select name="waist_join" :selected="old('waist_join', 'auto')" :options="[
                        'auto' => 'خودکار (بهترین راه)',
                        'gather' => 'چین دادن',
                        'true_side_seams' => 'راست‌سازی درز پهلو',
                    ]" />
                </x-field>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-bold text-stone-700">جای دوخت هر لبه (سانتی‌متر)</h3>
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach ($seamTags as $tag => $label)
                    <x-field :label="$label" :name="'seam_allowances.'.$tag">
                        <x-input type="number" step="0.1" min="0" max="10" :name="'seam_allowances['.$tag.']'"
                            :value="old('seam_allowances.'.$tag, $defaultSeamAllowances[$tag] ?? null)" />
                    </x-field>
                @endforeach
            </div>
        </div>

        {{-- پارامترهای مدلِ هر نقش --}}
        <div class="space-y-5">
            <h3 class="text-sm font-bold text-stone-700">تنظیم‌های ریزِ مدل‌ها</h3>
            <p class="-mt-2 text-xs text-stone-500">
                اندازه‌ها (قد لباس، قد آستین و…) در گام سه‌اند؛ این‌جا فقط چیزهایی
                است که کمتر لازم می‌شود: شیب سرشانه، گودی حلقه، سهم ساسون و مانند آن.
            </p>

            <template x-for="role in activeRoles()" :key="'role-' + role.role">
                <div class="space-y-3 rounded-2xl border border-stone-200 p-4">
                    <p class="text-xs font-semibold text-stone-600">
                        <span x-text="role.title"></span> — <span x-text="role.label"></span>
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <template x-for="field in fineFields(role)" :key="role.role + '-' + field.key">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-stone-700" x-text="field.label"></label>

                                <template x-if="field.type === 'select'">
                                    <select :name="'params[' + role.role + '][' + field.key + ']'"
                                        x-model="params[role.role][field.key]"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                        <template x-for="option in field.options" :key="option.value">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="field.type === 'toggle'">
                                    <select :name="'params[' + role.role + '][' + field.key + ']'"
                                        x-model="params[role.role][field.key]"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                        <option value="1">دارد</option>
                                        <option value="0">ندارد</option>
                                    </select>
                                </template>

                                <template x-if="field.type === 'number'">
                                    <input type="number" :name="'params[' + role.role + '][' + field.key + ']'"
                                        x-model="params[role.role][field.key]" :step="field.step" :min="field.min"
                                        :max="field.max"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </template>

                                <p class="text-xs text-stone-500" x-show="field.hint" x-text="field.hint"></p>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-stone-400" x-show="! fineFields(role).length">
                        تنظیم ریزی برای این مدل نمانده؛ اندازه‌هایش در گام سه است.
                    </p>
                </div>
            </template>

            <p class="text-xs text-stone-500" x-show="! activeRoles().length" x-cloak>
                هنوز مدلی انتخاب نشده است.
            </p>
        </div>

        {{-- پارامترهای سبک‌های انتخاب‌شده --}}
        <div class="space-y-5">
            <h3 class="text-sm font-bold text-stone-700">پارامترهای سبک‌ها</h3>

            <template x-for="(style, index) in styles" :key="'params-' + style.key">
                <div class="space-y-3 rounded-2xl border border-stone-200 p-4">
                    <p class="text-xs font-semibold text-stone-600" x-text="styleLabel(style.key)"></p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <template x-for="field in fieldsOf(styleSchema(style.key), style.params)"
                            :key="style.key + '-' + field.key">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-stone-700" x-text="field.label"></label>

                                <template x-if="field.type === 'select'">
                                    <select :name="'styles[' + index + '][params][' + field.key + ']'"
                                        x-model="style.params[field.key]"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                        <template x-for="option in field.options" :key="option.value">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="field.type === 'toggle'">
                                    <select :name="'styles[' + index + '][params][' + field.key + ']'"
                                        x-model="style.params[field.key]"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                        <option value="1">دارد</option>
                                        <option value="0">ندارد</option>
                                    </select>
                                </template>

                                <template x-if="field.type === 'number'">
                                    <input type="number" :name="'styles[' + index + '][params][' + field.key + ']'"
                                        x-model="style.params[field.key]" :step="field.step" :min="field.min"
                                        :max="field.max"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </template>

                                <p class="text-xs text-stone-500" x-show="field.hint" x-text="field.hint"></p>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-stone-400" x-show="! fieldsOf(styleSchema(style.key), style.params).length">
                        این سبک پارامتر تنظیم‌شدنی ندارد.
                    </p>
                </div>
            </template>

            <p class="text-xs text-stone-500" x-show="! styles.length">
                هنوز سبکی انتخاب نشده است؛ با انتخاب هر سبک، تنظیم‌های آن همین‌جا باز می‌شود.
            </p>
        </div>

        <x-field label="یادداشت" name="notes">
            <x-textarea name="notes" rows="2" placeholder="نکته‌ای که می‌خواهید روی این الگو بماند…" />
        </x-field>
    </div>
</x-advanced-section>
