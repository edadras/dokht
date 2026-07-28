@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-app-layout title="نقشه برش" wide>
    <x-page-header title="نقشه برش" :subtitle="$layout->summaryText()"
        :back="$project ? route('projects.show', $project) : route('projects.index')">
        <x-slot:actions>
            <x-button variant="secondary" icon="print" href="{{ route('cutting-layouts.print', $layout) }}"
                target="_blank">چاپ نقشه</x-button>

            @if ($project)
                <x-button variant="soft" icon="file" href="{{ route('tech-packs.show', $project) }}">کارت فنی</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($project)
        <x-step-nav :project="$project" current="9" />
    @endif

    {{-- پاسخ اصلی کاربر: چند متر پارچه بخرم و چقدر دور می‌ریزد --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="پارچه لازم" icon="layers" color="brand"
            :value="Jalali::digits(number_format($layout->requiredMeters(), 2)).' متر'"
            :hint="'طول '.Format::cm($layout->required_length_cm)" />

        <x-stat label="هزینه پارچه" icon="money" color="emerald" :value="Format::money($layout->estimatedCost())"
            :hint="$layout->fabric?->price_per_meter ? Format::money($layout->fabric->price_per_meter).' هر متر' : 'قیمت پارچه ثبت نشده'" />

        <x-stat label="دورریز" icon="alert" :color="$layout->waste_percent > 25 ? 'rose' : 'amber'"
            :value="Format::percent($layout->waste_percent, 1)"
            :hint="'مساحت قطعه‌ها '.Jalali::digits(number_format($layout->usedAreaCm2() / 10000, 2)).' متر مربع'" />

        <x-stat label="قطعه روی پارچه" icon="grid" color="sky"
            :value="Jalali::digits((string) $layout->placementCount())"
            :hint="$layout->folded ? 'پارچه تاشده — عرض مفید '.Format::cm($layout->usableWidthCm()) : 'پارچه باز — عرض '.Format::cm($layout->fabric_width_cm)" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="نقشه چیدمان روی پارچه" icon="scissors"
                :subtitle="'پارچه '.($layout->fabric?->displayName() ?? 'نامشخص').' • عرض '.Format::cm($layout->fabric_width_cm)">
                <div class="overflow-x-auto thin-scroll">
                    <div class="min-w-[320px]">{!! $plan !!}</div>
                </div>

                <p class="mt-4 text-xs text-stone-500">
                    خط‌چین داخل هر قطعه، خط دوخت است و خط بیرونی، خط برش (با جای دوخت). پیکان‌ها راستای پارچه را نشان می‌دهند.
                </p>
            </x-card>

            @if ($alternatives)
                <x-card title="مقایسه چیدمان‌ها" icon="chart"
                    subtitle="همین الگو با تنظیمات دیگر چقدر پارچه می‌خواهد">
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($alternatives as $alternative)
                            <div @class([
                                'rounded-xl border p-4',
                                'border-brand-300 bg-brand-50' => $alternative['key'] === 'base',
                                'border-stone-200 bg-white' => $alternative['key'] !== 'base',
                            ])>
                                <p class="text-sm font-semibold text-stone-800">{{ $alternative['label'] }}</p>
                                <p class="mt-1 text-xs text-stone-500">{{ $alternative['description'] }}</p>

                                <p class="mt-3 text-xl font-black text-stone-900">
                                    {{ Jalali::digits(number_format($alternative['required_meters'], 2)) }}
                                    <span class="text-sm font-medium text-stone-500">متر</span>
                                </p>

                                <div class="mt-2 space-y-1 text-xs text-stone-600">
                                    <p>دورریز {{ Format::percent($alternative['waste_percent'], 1) }}</p>
                                    <p>عرض مفید {{ Format::cm($alternative['usable_width_cm']) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($widthTable)
                <x-card title="مصرف در عرض‌های رایج پارچه" icon="ruler"
                    subtitle="پیش از خرید ببینید کدام عرض پارچه به‌صرفه‌تر است">
                    <div class="overflow-x-auto thin-scroll">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-stone-500">
                                <tr class="border-b border-stone-100">
                                    <th class="p-2 text-start">عرض پارچه</th>
                                    <th class="p-2 text-start">طول لازم</th>
                                    <th class="p-2 text-start">دورریز</th>
                                    <th class="p-2 text-start">هزینه</th>
                                    <th class="p-2 text-start">وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($widthTable as $row)
                                    <tr class="border-b border-stone-50 last:border-0">
                                        <td class="p-2 font-medium">{{ Format::cm($row['width_cm']) }}</td>
                                        <td class="p-2">
                                            {{ Jalali::digits(number_format($row['required_meters'], 2)) }} متر
                                        </td>
                                        <td class="p-2">{{ Format::percent($row['waste_percent'], 1) }}</td>
                                        <td class="p-2">{{ $row['cost'] ? Format::money($row['cost']) : '—' }}</td>
                                        <td class="p-2">
                                            @if ($row['fits'])
                                                <x-badge color="emerald" icon="check">همه قطعه‌ها جا می‌شوند</x-badge>
                                            @else
                                                <x-badge color="rose" icon="alert">قطعه‌ای جا نمی‌شود</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="بهره‌وری پارچه" icon="chart">
                <div class="space-y-4">
                    <x-meter label="بهره‌وری چیدمان" :value="$layout->efficiency" />
                    <x-meter label="دورریز" :value="$layout->waste_percent"
                        :color="$layout->waste_percent > 25 ? 'bg-rose-500' : 'bg-amber-500'" />

                    <dl class="space-y-2 border-t border-stone-100 pt-4 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-stone-500">مساحت پارچه</dt>
                            <dd>{{ Jalali::digits(number_format($layout->fabricAreaCm2() / 10000, 2)) }} متر مربع</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-stone-500">مساحت خالص قطعه‌ها</dt>
                            <dd>{{ Jalali::digits(number_format($layout->usedAreaCm2() / 10000, 2)) }} متر مربع</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-stone-500">جهت خواب پارچه</dt>
                            <dd>{{ $layout->respect_nap ? 'یک‌جهت بریده شود' : 'محدودیتی نیست' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-stone-500">تطبیق طرح</dt>
                            <dd>{{ $layout->match_stripes ? 'انجام شده' : 'لازم نبود' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-stone-500">تاریخ محاسبه</dt>
                            <dd>{{ Jalali::date($layout->created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </x-card>

            @if ($layout->warnings)
                <x-card title="نکته‌های برش" icon="alert">
                    <ul class="space-y-3">
                        @foreach ($layout->warnings as $warning)
                            <li class="flex items-start gap-2 text-sm text-stone-700">
                                <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                                <span>{{ $warning }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($ruleWarnings)
                <x-card title="هشدار قواعد طراحی" icon="info">
                    <ul class="space-y-3">
                        @foreach ($ruleWarnings as $rule)
                            <li class="text-sm">
                                <p class="font-semibold text-stone-800">{{ $rule['name'] ?? $rule['code'] ?? 'قاعده' }}</p>
                                <p class="text-stone-600">{{ $rule['message'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($project)
                <x-card title="محاسبه دوباره با تنظیمات دیگر" icon="refresh">
                    @include('cutting.partials.options-form', [
                        'project' => $project,
                        'layout' => $layout,
                        'fabrics' => $fabrics,
                    ])
                </x-card>

                @if ($project->cuttingLayouts()->count() > 1)
                    <x-card title="چیدمان‌های پیشین" icon="clock">
                        <ul class="divide-y divide-stone-100 text-sm">
                            @foreach ($project->cuttingLayouts as $previous)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span class="text-stone-600">
                                        {{ Jalali::digits(number_format($previous->requiredMeters(), 2)) }} متر •
                                        دورریز {{ Format::percent($previous->waste_percent, 1) }}
                                    </span>

                                    @if ($previous->is($layout))
                                        <x-badge color="brand">همین نقشه</x-badge>
                                    @else
                                        <a href="{{ route('cutting-layouts.show', $previous) }}"
                                            class="text-brand-600 hover:underline">دیدن</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
