@php
    use App\Support\Format;
@endphp

<x-app-layout title="مرحله ۱۱ — خروجی">
    <x-page-header title="کار تمام است؛ خروجی بگیرید" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="11" />

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="بسته‌ی خروجی پروژه" icon="download"
            subtitle="کارت فنی، نقشه‌ی برش و فایل الگو در یک بسته.">
            @if ($techPack)
                <div class="space-y-2">
                    <x-button :href="route('projects.export', $project)" size="lg" icon="download" class="w-full">
                        گرفتن خروجی کامل
                    </x-button>
                    <x-button :href="route('tech-packs.print', $project)" variant="secondary" icon="print"
                        class="w-full">چاپ کارت فنی</x-button>
                </div>
            @else
                <x-empty-state icon="file" title="اول کارت فنی را بسازید"
                    description="خروجی از روی کارت فنی ساخته می‌شود.">
                    <x-slot:action>
                        <x-button :href="route('projects.step', [$project, 'techpack'])" icon="file">
                            رفتن به کارت فنی
                        </x-button>
                    </x-slot:action>
                </x-empty-state>
            @endif
        </x-card>

        <x-card title="فایل الگو" icon="scissors" subtitle="برای چاپ در اندازه‌ی واقعی یا بردن به دستگاه برش.">
            @if ($project->pattern)
                <div class="space-y-2">
                    <x-button :href="route('patterns.print', $project->pattern)" variant="secondary" icon="print"
                        class="w-full">چاپ الگو روی کاغذ A4</x-button>
                    <x-button :href="route('patterns.export', [$project->pattern, 'svg'])" variant="secondary"
                        icon="download" class="w-full">خروجی SVG</x-button>
                    <x-button :href="route('patterns.export', [$project->pattern, 'dxf'])" variant="secondary"
                        icon="download" class="w-full">خروجی DXF (دستگاه برش)</x-button>
                </div>
            @else
                <p class="text-sm text-stone-500">این پروژه الگو ندارد.</p>
            @endif
        </x-card>

        @if ($layout)
            <x-card title="نقشه‌ی برش" icon="grid" class="lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-stat label="طول لازم پارچه" :value="Format::number($layout->requiredMeters(), 2).' متر'"
                        icon="ruler" />
                    <x-stat label="بازده" :value="Format::percent($layout->efficiency)" icon="chart" color="emerald" />
                    <x-stat label="هزینه‌ی پارچه" :value="Format::money($layout->estimatedCost())" icon="money"
                        color="amber" />
                </div>

                <x-button :href="route('cutting-layouts.print', $layout)" variant="secondary" icon="print"
                    class="mt-4">چاپ نقشه‌ی برش</x-button>
            </x-card>
        @endif
    </div>

    <x-card class="mt-5">
        <p class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">
            <b>تمام شد.</b>
            این پروژه از انتخاب مدل تا خروجی قابل چاپ کامل شده است. اگر لباس دیگری با همین مشخصات
            می‌خواهید، از صفحه‌ی الگو دکمه‌ی «تکثیر» را بزنید تا از نو شروع نکنید.
        </p>
    </x-card>
</x-app-layout>
