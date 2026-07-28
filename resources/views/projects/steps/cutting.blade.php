@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-app-layout title="مرحله ۹ — برش">
    <x-page-header title="پارچه را چطور ببریم؟" :subtitle="$project->name" :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="9" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-card title="چیدمان برش" icon="scissors"
                subtitle="سامانه قطعه‌ها را روی عرض پارچه می‌چیند تا کمترین دورریز را داشته باشید.">
                @if ($layout)
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-stat label="طول لازم پارچه" :value="Format::number($layout->requiredMeters(), 2).' متر'"
                            icon="ruler" />
                        <x-stat label="بازده چیدمان" :value="Format::percent($layout->efficiency)" icon="chart"
                            color="emerald" />
                        <x-stat label="دورریز" :value="Format::percent($layout->waste_percent)" icon="box"
                            color="amber" />
                    </div>

                    <dl class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">عرض پارچه</dt>
                            <dd class="font-medium">{{ Format::cm($layout->fabric_width_cm) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">هزینه‌ی تخمینی پارچه</dt>
                            <dd class="font-medium">{{ Format::money($layout->estimatedCost()) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">ساخته شده</dt>
                            <dd class="font-medium">{{ Jalali::dateTime($layout->created_at) }}</dd>
                        </div>
                    </dl>

                    @foreach ($layout->warnings ?? [] as $warning)
                        <x-alert type="warning" class="mt-3">
                            {{ is_array($warning) ? $warning['message'] ?? '' : $warning }}
                        </x-alert>
                    @endforeach

                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-button :href="route('cutting-layouts.show', $layout)" icon="grid">دیدن نقشه‌ی برش</x-button>
                        <x-button :href="route('cutting-layouts.print', $layout)" variant="secondary" icon="print">
                            چاپ
                        </x-button>
                    </div>
                @else
                    <x-empty-state icon="scissors" title="هنوز چیدمان برش ساخته نشده"
                        description="با یک کلیک قطعه‌ها روی عرض پارچه‌ی انتخابی چیده می‌شوند و طول لازم حساب می‌شود." />
                @endif
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card title="ساختن چیدمان" icon="sparkles">
                @if (! $project->pattern)
                    <p class="text-sm text-stone-500">اول باید الگو ساخته شود.</p>
                    <x-button :href="route('projects.step', [$project, 'pattern'])" variant="secondary" size="sm"
                        class="mt-3 w-full">رفتن به مرحله‌ی الگو</x-button>
                @else
                    <form method="POST" action="{{ route('projects.cutting', $project) }}">
                        @csrf
                        <x-button type="submit" size="lg" icon="scissors" class="w-full">
                            {{ $layout ? 'چیدمان را دوباره بساز' : 'چیدمان برش را بساز' }}
                        </x-button>
                    </form>

                    <p class="mt-3 text-xs leading-5 text-stone-500">
                        عرض پارچه‌ی
                        {{ $project->fabric?->displayName() ?? 'انتخاب‌شده' }}
                        و جهت‌داری آن (خواب پارچه و طرح راه‌راه) خودکار در نظر گرفته می‌شود.
                    </p>
                @endif
            </x-card>

            <form method="POST" action="{{ route('projects.step.update', [$project, 'cutting']) }}">
                @csrf
                @method('PUT')
                <x-button type="submit" variant="secondary" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>
            </form>
        </div>
    </div>
</x-app-layout>
