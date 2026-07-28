{{-- فرم متعلقات: پنج فیلد ساده، بی هیچ پیچیدگی --}}
@php($isEdit = $material->exists)

<form method="POST" action="{{ $isEdit ? route('materials.update', $material) : route('materials.store') }}"
    class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="نوع" name="kind" required>
            <x-select name="kind" :options="$kinds" :selected="$material->kind" />
        </x-field>

        <x-field label="نام" name="name" required>
            <x-input name="name" :value="$material->name" required placeholder="مثلاً نخ دوخت مشکی" />
        </x-field>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="مشخصات" name="spec" hint="مثلاً شماره، اندازه یا عرض">
            <x-input name="spec" :value="$material->spec" placeholder="مثلاً ۵۰۰ متری" />
        </x-field>

        <x-field label="رنگ" name="color">
            <x-input name="color" :value="$material->color" placeholder="مثلاً سرمه‌ای" />
        </x-field>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="موجودی" name="stock">
            <x-input type="number" name="stock" :value="$material->exists ? $material->stock : null" step="0.5" min="0"
                placeholder="۰" />
        </x-field>

        <x-field label="واحد" name="unit">
            <x-input name="unit" :value="$material->unit ?: 'عدد'" placeholder="عدد" />
        </x-field>

        <x-field label="قیمت واحد" name="price" suffix="تومان">
            <x-input type="number" name="price" :value="$material->price ? (int) $material->price : null" step="500" min="0" />
        </x-field>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
        <x-button href="{{ route('materials.index') }}" variant="ghost">بازگشت</x-button>
        <x-button type="submit" icon="check">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</x-button>
    </div>
</form>
