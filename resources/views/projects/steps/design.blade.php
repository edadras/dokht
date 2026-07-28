@php
    use App\Models\GarmentType;
@endphp

<x-app-layout title="مرحله ۱ — انتخاب مدل">
    <x-page-header title="چه لباسی می‌دوزیم؟" :subtitle="$project->name" :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="1" />

    <form method="POST" action="{{ route('projects.step.update', [$project, 'design']) }}" class="space-y-5"
        x-data="{ selected: {{ (int) old('garment_type_id', $project->garment_type_id) }} }">
        @csrf
        @method('PUT')

        <x-card title="مدل لباس را انتخاب کنید" icon="shirt"
            subtitle="روی یکی از مدل‌ها بزنید؛ آزادی و قطعه‌های همان مدل خودکار انتخاب می‌شود.">
            @if ($grouped->isEmpty())
                <x-empty-state icon="shirt" title="هنوز مدلی در سامانه ثبت نشده"
                    description="مدیر سامانه باید انواع لباس را اضافه کند." />
            @else
                <div class="space-y-6">
                    @foreach ($grouped as $category => $types)
                        <div>
                            <p class="mb-3 text-sm font-semibold text-stone-500">
                                {{ GarmentType::CATEGORIES[$category] ?? $category }}
                            </p>

                            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($types as $type)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="garment_type_id" value="{{ $type->id }}"
                                            x-model.number="selected" class="peer sr-only">

                                        <span
                                            class="flex h-full flex-col items-center gap-2 rounded-2xl border-2 border-stone-200 bg-white p-4 text-center transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
                                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 text-stone-500">
                                                <x-icon :name="$type->icon ?: 'shirt'" class="h-6 w-6" />
                                            </span>
                                            <span class="text-sm font-semibold text-stone-800">{{ $type->name_fa }}</span>
                                            <span class="text-[11px] leading-4 text-stone-400">
                                                {{ implode(' • ', array_slice($type->partLabels(), 0, 3)) }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @error('garment_type_id')
                <p class="mt-4 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </x-card>

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نام پروژه" name="name" required>
                    <x-input name="name" :value="$project->name" />
                </x-field>

                <div class="flex items-end">
                    <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>
                </div>
            </div>

            <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                <b>سامانه چه کاری کرد؟</b>
                با انتخاب مدل، فهرست قطعه‌های لازم (بالاتنه، آستین، یقه و…) و مقدار آزادی متعارف همان مدل
                برای الگو در نظر گرفته می‌شود. اگر آزادی خاصی می‌خواهید از بخش پایین تغییرش بدهید.
            </p>
        </x-card>

        <x-advanced-section title="آزادی دلخواه" description="مقدار گشادی لباس نسبت به بدن، بر حسب سانتی‌متر.">
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach (['bust' => 'سینه', 'waist' => 'کمر', 'hip' => 'باسن', 'bicep' => 'بازو'] as $key => $label)
                    <x-field :label="$label" :name="'ease.'.$key" suffix="س‌م">
                        <x-input type="number" step="0.5" name="ease[{{ $key }}]"
                            :value="$project->effectiveEase()[$key] ?? null" />
                    </x-field>
                @endforeach
            </div>
        </x-advanced-section>
    </form>
</x-app-layout>
