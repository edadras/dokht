@php
    /** فرم مشترک ساخت و ویرایش الگوی پایه. */
    $rows = old('params', $params) ?: [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card title="شناسه الگوی پایه" icon="ruler">
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نام فارسی" name="name_fa" required>
                    <x-input name="name_fa" :value="$template->name_fa" required autofocus />
                </x-field>

                <x-field label="نوع لباس" name="garment_type_id" required>
                    <x-select name="garment_type_id" :selected="$template->garment_type_id" required placeholder="انتخاب کنید"
                        :options="$garmentTypes->pluck('name_fa', 'id')->all()" />
                </x-field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="شناسه" name="code" required hint="لاتین و بی‌فاصله؛ مثل bodice-block">
                    <x-input name="code" :value="$template->code" required dir="ltr" class="text-left" />
                </x-field>

                <x-field label="تولیدکننده هندسه" name="generator" required
                    hint="کلاسی که نقشه قطعه‌ها را می‌سازد.">
                    @if ($generators !== [])
                        <x-select name="generator" :options="$generators" :selected="$template->generator" required
                            placeholder="انتخاب کنید" />
                    @else
                        <x-input name="generator" :value="$template->generator" required dir="ltr" class="text-left" />
                    @endif
                </x-field>
            </div>

            <x-field label="توضیح" name="description" hint="این الگو برای چه مدلی است و چه چیزی را می‌سازد؟">
                <x-textarea name="description" :value="$template->description" rows="3" />
            </x-field>
        </div>
    </x-card>

    <x-advanced-section title="پارامترهای الگو"
        description="هر پارامتر یک عدد قابل تنظیم برای کاربر است؛ مثل «بلندی دامن» یا «پهنای یقه».">
        <div x-data="{ rows: @js(array_values($rows)) }" class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-stone-500">کلید لاتین است و برچسب فارسی به کاربر نشان داده می‌شود.</p>
                <x-button type="button" size="sm" variant="secondary" icon="plus"
                    x-on:click="rows.push({ key: '', label: '', default: 0, min: 0, max: 100, step: 0.5 })">
                    افزودن پارامتر
                </x-button>
            </div>

            <div class="overflow-x-auto thin-scroll">
                <table class="w-full min-w-max text-sm">
                    <thead class="text-xs text-stone-500">
                        <tr>
                            <th class="px-2 py-2 text-start font-medium">کلید</th>
                            <th class="px-2 py-2 text-start font-medium">برچسب فارسی</th>
                            <th class="px-2 py-2 text-start font-medium">پیش‌فرض</th>
                            <th class="px-2 py-2 text-start font-medium">کمینه</th>
                            <th class="px-2 py-2 text-start font-medium">بیشینه</th>
                            <th class="px-2 py-2 text-start font-medium">گام</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td class="px-2 py-1.5">
                                    <input type="text" dir="ltr" x-model="row.key" :name="`params[${index}][key]`"
                                        class="w-36 rounded-xl border border-stone-300 bg-white px-3 py-2 text-left text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="text" x-model="row.label" :name="`params[${index}][label]`"
                                        class="w-48 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.1" x-model="row.default"
                                        :name="`params[${index}][default]`"
                                        class="w-24 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.1" x-model="row.min" :name="`params[${index}][min]`"
                                        class="w-24 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.1" x-model="row.max" :name="`params[${index}][max]`"
                                        class="w-24 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="number" step="0.05" min="0.01" x-model="row.step"
                                        :name="`params[${index}][step]`"
                                        class="w-20 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </td>
                                <td class="px-2 py-1.5">
                                    <button type="button" x-on:click="rows.splice(index, 1)"
                                        class="rounded-lg p-2 text-stone-400 hover:bg-stone-100 hover:text-rose-600">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p x-show="rows.length === 0" class="text-sm text-stone-500">پارامتری تعریف نشده است.</p>
        </div>
    </x-advanced-section>

    <x-card title="نمایش در کتابخانه" icon="settings">
        <div class="grid items-end gap-4 sm:grid-cols-2">
            <x-field label="ترتیب نمایش" name="sort" hint="عدد کمتر بالاتر می‌آید.">
                <x-input type="number" name="sort" :value="$template->sort ?? 0" min="0" max="9999" />
            </x-field>

            <label class="flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-3">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $template->is_public ?? true))
                    class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                <span class="text-sm font-medium text-stone-700">عمومی (در کتابخانه همه کارگاه‌ها دیده شود)</span>
            </label>
        </div>

        @if ($template->exists)
            <div class="mt-5 border-t border-stone-100 pt-5">
                <p class="text-sm font-semibold text-stone-800">پیش‌نمایش</p>
                <p class="mt-1 text-xs text-stone-500">
                    الگو با سایز استاندارد
                    {{ \App\Support\Jalali::digits(\App\Http\Controllers\Admin\PatternTemplateController::PREVIEW_SIZE) }}
                    ساخته می‌شود و تصویر آن در کارت کتابخانه نشان داده می‌شود.
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <div
                        class="flex h-32 w-32 items-center justify-center rounded-xl border border-stone-200 bg-stone-50 p-2">
                        @if ($template->preview_svg)
                            <div class="max-h-full [&>svg]:max-h-28 [&>svg]:w-auto">{!! $template->preview_svg !!}</div>
                        @else
                            <x-icon name="ruler" class="h-8 w-8 text-stone-300" />
                        @endif
                    </div>

                    <x-button type="submit" name="render_preview" value="1" variant="soft" icon="refresh">
                        ذخیره و ساخت پیش‌نمایش
                    </x-button>
                </div>
            </div>
        @endif
    </x-card>

    <div class="flex flex-wrap justify-end gap-2">
        <x-button href="{{ route('admin.templates.index') }}" variant="secondary">انصراف</x-button>
        <x-button type="submit" icon="check">{{ $submit }}</x-button>
    </div>
</form>
