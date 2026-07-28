@php
    use App\Models\Simulation;
    use App\Support\Format;
    use App\Support\Jalali;

    $zones = $analysis['zones'] ?? [];
    $score = $analysis['fit_score'] ?? null;

    $cardLabels = [
        'pattern_quality' => 'کیفیت الگو',
        'fabric_compatibility' => 'سازگاری پارچه',
        'fit' => 'تناسب با بدن',
        'cutting_efficiency' => 'بازده برش',
        'production_readiness' => 'آمادگی تولید',
    ];
@endphp

<x-app-layout title="مرحله ۸ — تست تناسب" wide>
    <x-page-header title="لباس به تن می‌نشیند؟" :subtitle="$project->name" :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="8" />

    @if ($zones === [])
        <x-empty-state icon="check" title="هنوز تست تناسبی انجام نشده"
            description="در مرحله‌ی قبل دکمه‌ی «شبیه‌سازی کن» را بزنید تا نقشه‌ی تنگی و گشادی ساخته شود.">
            <x-slot:action>
                <x-button :href="route('projects.step', [$project, 'simulation'])" icon="cube">رفتن به نمای سه‌بعدی</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- جدول نواحی --}}
                <x-card title="نقشه‌ی تناسب" icon="ruler" padding="p-0"
                    :subtitle="'حالت بدن: '.(Simulation::POSES[$analysis['pose'] ?? 'stand'] ?? 'ایستاده')">
                    <div class="thin-scroll overflow-x-auto">
                        <table class="w-full min-w-max text-sm">
                            <thead class="bg-stone-50 text-xs text-stone-500">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium">ناحیه</th>
                                    <th class="px-4 py-3 text-start font-medium">اندازه‌ی لباس</th>
                                    <th class="px-4 py-3 text-start font-medium">اندازه‌ی بدن</th>
                                    <th class="px-4 py-3 text-start font-medium">آزادی</th>
                                    <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                                    <th class="px-4 py-3 text-start font-medium">فشار</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($zones as $zone)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-stone-800">{{ $zone['label'] ?? $zone['key'] }}</span>
                                            <span class="mt-0.5 block max-w-xs text-[11px] leading-5 text-stone-400">
                                                {{ $zone['note'] ?? '' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-stone-600">{{ Format::cm($zone['garment_cm'] ?? null) }}</td>
                                        <td class="px-4 py-3 text-stone-600">{{ Format::cm($zone['body_cm'] ?? null) }}</td>
                                        <td class="px-4 py-3 font-semibold"
                                            style="color: {{ $zone['color'] ?? '#57534e' }}">
                                            {{ ($zone['ease_cm'] ?? 0) > 0 ? '+' : '' }}{{ Jalali::digits((string) ($zone['ease_cm'] ?? 0)) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                                style="background: {{ $zone['color'] ?? '#6b7280' }}">
                                                {{ Simulation::levelLabel($zone['level'] ?? 'good') }}
                                            </span>
                                        </td>
                                        <td class="w-32 px-4 py-3">
                                            <x-meter :value="round(($zone['pressure'] ?? 0) * 100)" :show-value="false"
                                                :color="'bg-stone-400'" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>

                {{-- هشدارها --}}
                @if (! empty($analysis['warnings']))
                    <x-card title="نکته‌هایی که باید بدانید" icon="alert">
                        <div class="space-y-3">
                            @foreach ($analysis['warnings'] as $warning)
                                <x-alert type="warning">{{ $warning }}</x-alert>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                {{-- پیشنهادهای یک‌کلیکی --}}
                @if (! empty($analysis['suggestions']))
                    <x-card title="اصلاح با یک کلیک" icon="sparkles"
                        subtitle="هر دکمه آزادی همان ناحیه را در الگو تغییر می‌دهد و تست را از نو اجرا می‌کند.">
                        <ul class="divide-y divide-stone-100">
                            @foreach ($analysis['suggestions'] as $suggestion)
                                <li class="flex flex-wrap items-center gap-3 py-3">
                                    <span class="min-w-0 flex-1 text-sm text-stone-700">{{ $suggestion['text'] ?? '' }}</span>

                                    @if (! empty($suggestion['action']['key']))
                                        <form method="POST"
                                            action="{{ route('projects.step.update', [$project, 'fit']) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action_type" value="increase_ease">
                                            <input type="hidden" name="action_key"
                                                value="{{ $suggestion['action']['key'] }}">
                                            <input type="hidden" name="action_value"
                                                value="{{ $suggestion['action']['value'] ?? 1 }}">
                                            <x-button type="submit" variant="soft" size="sm" icon="check">
                                                اعمال کن
                                            </x-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </div>

            <div class="space-y-5">
                {{-- امتیاز تناسب --}}
                <x-card title="امتیاز تناسب" icon="star">
                    <div class="flex items-center gap-4">
                        <span class="text-4xl font-black text-brand-700">
                            {{ $score === null ? '—' : Jalali::digits((string) $score) }}
                        </span>
                        <div class="text-sm">
                            <p class="font-semibold text-stone-800">از ۱۰۰</p>
                            <p class="text-xs text-stone-500">
                                @if ($score === null)
                                    هنوز محاسبه نشده
                                @elseif ($score >= 85)
                                    تناسب بسیار خوب است
                                @elseif ($score >= 70)
                                    قابل قبول؛ با اصلاح کوچک بهتر می‌شود
                                @elseif ($score >= 50)
                                    چند ناحیه نیاز به اصلاح دارد
                                @else
                                    الگو باید بازبینی شود
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($score !== null)
                        <x-meter class="mt-4" :value="$score" label="تناسب کلی" />
                    @endif
                </x-card>

                {{-- کارنامه‌ی کیفیت --}}
                <x-card title="کارنامه‌ی کیفیت" icon="chart" subtitle="هر عدد از ۱۰۰.">
                    @if ($scorecard)
                        <div class="space-y-4">
                            @foreach ($cardLabels as $key => $label)
                                <x-meter :label="$label" :value="$scorecard[$key] ?? 0" />
                            @endforeach

                            <div class="flex items-center justify-between rounded-xl bg-stone-50 px-4 py-3">
                                <span class="text-sm font-semibold text-stone-700">امتیاز کلی</span>
                                <span class="text-xl font-black text-brand-700">
                                    {{ Jalali::digits((string) ($scorecard['overall'] ?? 0)) }}
                                </span>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-stone-500">کارنامه پس از انتخاب پارچه و ساخت الگو کامل می‌شود.</p>
                    @endif
                </x-card>

                @if ($simulation)
                    <x-card title="این گزارش" icon="clock">
                        <p class="text-sm text-stone-600">{{ Jalali::dateTime($simulation->created_at) }}</p>
                        <div class="mt-3 space-y-2">
                            <x-button :href="route('simulations.show', $simulation)" variant="secondary" size="sm"
                                icon="eye" class="w-full">گزارش کامل با نمای سه‌بعدی</x-button>
                            <x-button :href="route('projects.step', [$project, 'simulation'])" variant="secondary"
                                size="sm" icon="refresh" class="w-full">شبیه‌سازی دوباره</x-button>
                        </div>
                    </x-card>
                @endif

                <form method="POST" action="{{ route('projects.step.update', [$project, 'fit']) }}">
                    @csrf
                    @method('PUT')
                    <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه به برش</x-button>
                </form>
            </div>
        </div>

        <x-card class="mt-5">
            <p class="rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                <b>سامانه چه کاری کرد؟</b>
                اندازه‌ی خودِ لباس را از قطعه‌های الگو حساب کرد، با اندازه‌ی بدن مقایسه کرد و اختلاف را
                با توجه به کشسانی پارچه و حالت بدن به چهار وضعیت رنگی تبدیل کرد:
                <span class="font-semibold text-rose-600">تنگ</span>،
                <span class="font-semibold text-amber-600">چسبان</span>،
                <span class="font-semibold text-emerald-600">مناسب</span> و
                <span class="font-semibold text-sky-600">گشاد</span>.
                برای پارچه‌ی کشی، کوچک‌تر بودن لباس از بدن اشکالی ندارد؛ برای پارچه‌ی بی‌کشش دارد.
            </p>
        </x-card>
    @endif
</x-app-layout>
