@php
    use App\Support\Jalali;
@endphp

<x-app-layout title="مرحله ۱۰ — کارت فنی">
    <x-page-header title="کارت فنی لباس را بسازیم" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="10" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-card title="کارت فنی" icon="file"
                subtitle="یک برگه که همه‌ی چیزهای لازم برای دوخت را کنار هم می‌آورد: اندازه‌ها، پارچه، قطعه‌ها، جای دوخت و متعلقات.">
                @if ($techPack)
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-badge color="brand" icon="file">
                                نسخه‌ی {{ Jalali::digits($techPack->version) }}
                            </x-badge>
                            <span class="text-sm text-stone-500">{{ Jalali::dateTime($techPack->created_at) }}</span>
                        </div>

                        <ul class="flex flex-wrap gap-2 text-xs text-stone-600">
                            @foreach (array_keys((array) $techPack->data) as $section)
                                <li class="rounded-full bg-stone-100 px-2.5 py-1">{{ $section }}</li>
                            @endforeach
                        </ul>

                        <div class="flex flex-wrap gap-2">
                            <x-button :href="route('tech-packs.show', $project)" icon="eye">دیدن کارت فنی</x-button>
                            <x-button :href="route('tech-packs.print', $project)" variant="secondary" icon="print">
                                چاپ
                            </x-button>
                        </div>
                    </div>

                    @if ($techPacks->count() > 1)
                        <div class="mt-5 border-t border-stone-100 pt-4">
                            <p class="mb-2 text-xs font-semibold text-stone-500">نسخه‌های قبلی</p>
                            <ul class="space-y-1 text-sm text-stone-600">
                                @foreach ($techPacks->skip(1) as $old)
                                    <li class="flex items-center justify-between">
                                        <span>نسخه‌ی {{ Jalali::digits($old->version) }}</span>
                                        <span class="text-xs text-stone-400">{{ Jalali::date($old->created_at) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @else
                    <x-empty-state icon="file" title="کارت فنی ساخته نشده"
                        description="با یک کلیک همه‌ی اطلاعات این پروژه در یک برگه‌ی آماده‌ی چاپ جمع می‌شود." />
                @endif
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card title="ساختن کارت فنی" icon="sparkles">
                <form method="POST" action="{{ route('tech-packs.store', $project) }}">
                    @csrf
                    <x-button type="submit" size="lg" icon="file" class="w-full">
                        {{ $techPack ? 'نسخه‌ی تازه بساز' : 'کارت فنی را بساز' }}
                    </x-button>
                </form>

                <p class="mt-3 text-xs leading-5 text-stone-500">
                    هر بار که چیزی در پروژه عوض شد، یک نسخه‌ی تازه بسازید تا تاریخچه بماند.
                </p>
            </x-card>

            <form method="POST" action="{{ route('projects.step.update', [$project, 'techpack']) }}">
                @csrf
                @method('PUT')
                <x-button type="submit" variant="secondary" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>
            </form>
        </div>
    </div>
</x-app-layout>
