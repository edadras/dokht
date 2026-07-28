@php
    use App\Models\GarmentType;
    use App\Support\Jalali;
@endphp

<x-app-layout title="پروژه جدید">
    <div class="mx-auto max-w-2xl">
        <x-page-header title="یک لباس تازه" subtitle="فقط یک نام بنویسید؛ بقیه‌ی چیزها را مرحله‌به‌مرحله می‌پرسیم."
            :back="route('projects.index')" />

        <form method="POST" action="{{ route('projects.store') }}" class="space-y-5">
            @csrf

            <x-card>
                <div class="space-y-5">
                    <x-field label="این لباس را چه بنامیم؟" name="name" required
                        hint="مثلاً «مانتو اداری خانم احمدی» — هر اسمی که بعداً خودتان بشناسید.">
                        <x-input name="name" placeholder="نام پروژه" autofocus />
                    </x-field>

                    <p class="rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
                        همین یک نام کافی است. با زدن دکمه‌ی زیر مستقیم به مرحله‌ی اول می‌روید و
                        در هر مرحله فقط به یک سؤال جواب می‌دهید.
                    </p>

                    <x-button type="submit" size="lg" icon="arrow-left" class="w-full">
                        ساختن پروژه و شروع مرحله‌ی اول
                    </x-button>
                </div>
            </x-card>

            <x-advanced-section title="اگر همین حالا می‌دانید" description="مشتری، مدل و سایز را می‌توانید از الان انتخاب کنید.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="مشتری" name="customer_id" hint="تازه‌ترین مشتری‌ها بالای فهرست هستند.">
                        <x-select name="customer_id" placeholder="بعداً انتخاب می‌کنم"
                            :options="$customers->pluck('name', 'id')->all()" />
                    </x-field>

                    <x-field label="سایز استاندارد" name="size" hint="اگر اندازه‌ی دقیق ندارید.">
                        <x-select name="size" placeholder="بعداً انتخاب می‌کنم"
                            :options="collect($sizes)->mapWithKeys(fn ($s) => [$s => Jalali::digits($s)])->all()" />
                    </x-field>

                    <x-field label="مدل لباس" name="garment_type_id" class="sm:col-span-2">
                        <x-select name="garment_type_id" placeholder="بعداً انتخاب می‌کنم">
                            @foreach ($garmentTypes->groupBy('category') as $category => $types)
                                <optgroup label="{{ GarmentType::CATEGORIES[$category] ?? $category }}">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->id }}" @selected(old('garment_type_id') == $type->id)>
                                            {{ $type->name_fa }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </x-select>
                    </x-field>
                </div>
            </x-advanced-section>
        </form>
    </div>
</x-app-layout>
