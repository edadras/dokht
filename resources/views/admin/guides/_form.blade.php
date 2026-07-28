@php
    /** فرم مشترک ساخت و ویرایش راهنمای آموزشی. */
    $tips = array_values(old('tips', $guide->tips ?: ['']));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
    x-data="{ scope: @js(old('scope', $guide->scope ?? 'general')) }">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card title="این راهنما درباره چیست؟" icon="book">
        <div class="space-y-4">
            <x-field label="نوع راهنما" name="scope" required>
                <select name="scope" x-model="scope" required
                    class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach (\App\Models\Guide::SCOPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>

            <div x-show="scope === 'fabric_type'" x-cloak>
                <x-field label="نوع پارچه" name="fabric_type_id">
                    <x-select name="fabric_type_id" :options="$fabricTypes" :selected="$guide->fabric_type_id"
                        placeholder="انتخاب کنید" />
                </x-field>
            </div>

            <div x-show="scope === 'garment_type'" x-cloak>
                <x-field label="نوع لباس" name="garment_type_id">
                    <x-select name="garment_type_id" :options="$garmentTypes" :selected="$guide->garment_type_id"
                        placeholder="انتخاب کنید" />
                </x-field>
            </div>

            <x-field label="عنوان" name="title" required>
                <x-input name="title" :value="$guide->title" required />
            </x-field>

            <x-field label="خلاصه یک‌خطی" name="summary" hint="در فهرست آموزش زیر عنوان دیده می‌شود.">
                <x-input name="summary" :value="$guide->summary" />
            </x-field>

            <x-field label="متن راهنما" name="body" hint="هر پاراگراف را در یک خط جدا بنویسید.">
                <x-textarea name="body" :value="$guide->body" rows="10" />
            </x-field>
        </div>
    </x-card>

    <x-card title="نکته‌های کوتاه" icon="sparkles" subtitle="نکته‌هایی که کنار متن به‌صورت فهرست نشان داده می‌شوند.">
        <div x-data="{ tips: @js($tips) }" class="space-y-3">
            <template x-for="(tip, index) in tips" :key="index">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="tips[index]" :name="`tips[${index}]`"
                        placeholder="مثل: قبل از برش، پارچه را بشویید."
                        class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                    <button type="button" x-on:click="tips.splice(index, 1)"
                        class="rounded-lg p-2 text-stone-400 hover:bg-stone-100 hover:text-rose-600">
                        <x-icon name="trash" class="h-4 w-4" />
                    </button>
                </div>
            </template>

            <x-button type="button" size="sm" variant="secondary" icon="plus" x-on:click="tips.push('')">
                افزودن نکته
            </x-button>
        </div>
    </x-card>

    <x-card title="ترتیب نمایش" icon="settings">
        <x-field label="ترتیب" name="sort" hint="عدد کمتر بالاتر می‌آید." class="sm:max-w-xs">
            <x-input type="number" name="sort" :value="$guide->sort ?? 0" min="0" max="9999" />
        </x-field>
    </x-card>

    <div class="flex flex-wrap justify-end gap-2">
        <x-button href="{{ route('admin.guides.index') }}" variant="secondary">انصراف</x-button>
        <x-button type="submit" icon="check">{{ $submit }}</x-button>
    </div>
</form>
