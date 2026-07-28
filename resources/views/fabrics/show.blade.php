@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-app-layout :title="$fabric->name">
    <x-page-header :title="$fabric->name" :subtitle="$fabric->typeName().($fabric->color ? ' — '.$fabric->color : '')"
        :back="route('fabrics.index')">
        <x-slot:actions>
            <x-button href="{{ route('assistant.index', ['fabric_id' => $fabric->id]) }}" variant="secondary"
                icon="sparkles">پرسش از دستیار</x-button>
            <x-button href="{{ route('fabrics.edit', $fabric) }}" icon="edit">ویرایش</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="شناسنامه پارچه" icon="layers">
                <div class="flex flex-wrap gap-5">
                    @include('fabrics.partials.swatch', ['fabric' => $fabric, 'size' => 'h-24 w-24'])

                    <div class="min-w-52 flex-1 space-y-2 text-sm">
                        <div class="flex flex-wrap gap-1.5">
                            <x-badge color="brand">{{ $profile->drapeLabel() }}</x-badge>
                            <x-badge color="clay">{{ $profile->weightLabel() }}</x-badge>
                            <x-badge>{{ $profile->thicknessLabel() }}</x-badge>
                            @if ($fabric->fabricType?->familyLabel())
                                <x-badge color="violet">{{ $fabric->fabricType->familyLabel() }}</x-badge>
                            @endif
                            @if ($profile->isSheer())
                                <x-badge color="amber">شفاف</x-badge>
                            @endif
                            @if ($profile->isStretchy())
                                <x-badge color="emerald">کشی</x-badge>
                            @endif
                        </div>

                        @if ($fabric->fabricType?->compositionLabel())
                            <p class="text-stone-600">ترکیب الیاف: {{ $fabric->fabricType->compositionLabel() }}</p>
                        @endif

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-stone-600 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-stone-400">عرض</dt>
                                <dd class="font-semibold">{{ Format::cm($fabric->effectiveWidth()) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-stone-400">موجودی</dt>
                                <dd class="font-semibold">{{ Format::number($fabric->stock_meters, 1) }} متر</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-stone-400">قیمت هر متر</dt>
                                <dd class="font-semibold">{{ Format::money($fabric->price_per_meter) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-stone-400">طرح روی پارچه</dt>
                                <dd class="font-semibold">{{ $fabric->surfacePatternLabel() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-stone-400">سختی کار</dt>
                                <dd class="font-semibold">{{ Format::ratio($profile->difficulty()) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-stone-400">آب‌رفت</dt>
                                <dd class="font-semibold">{{ Format::percent($profile->get('shrinkage')) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <x-meter label="لختی" :value="$profile->get('drape')" :max="1" />
                    <x-meter label="سفتی" :value="$profile->get('stiffness')" :max="1" />
                    <x-meter label="کشسانی عرض" :value="$profile->get('stretch_weft')" :max="100" />
                    <x-meter label="شفافیت" :value="$profile->get('transparency')" :max="1" />
                    <x-meter label="برگشت‌پذیری" :value="$profile->get('recovery')" :max="100" />
                    <x-meter label="تنفس‌پذیری" :value="$profile->get('breathability')" :max="1" />
                </div>

                @if ($fabric->notes)
                    <p class="mt-5 rounded-xl bg-stone-50 p-3 text-sm text-stone-600">{{ $fabric->notes }}</p>
                @endif
            </x-card>

            <x-card title="هشدارهای برش" subtitle="این نکته‌ها از قواعد طراحی و شناسنامه همین پارچه ساخته شده‌اند."
                icon="scissors">
                @include('fabrics.partials.rules', [
                    'results' => array_merge($fabricResults, $cuttingResults),
                    'empty' => 'این پارچه هنگام برش دردسر خاصی ندارد.',
                ])
            </x-card>

            <x-card title="راهنمای دوخت" icon="check-circle">
                @include('fabrics.partials.rules', [
                    'results' => $productionResults,
                    'empty' => 'با تنظیمات معمول چرخ می‌توانید این پارچه را بدوزید.',
                ])

                @if ($fabric->fabricType?->sewing)
                    <dl class="mt-5 space-y-2 border-t border-stone-100 pt-4 text-sm">
                        @foreach (['needle' => 'سوزن', 'stitch' => 'کوک', 'seam' => 'درزبندی', 'tip' => 'نکته'] as $key => $label)
                            @if (! empty($fabric->fabricType->sewing[$key]))
                                <div class="flex gap-2">
                                    <dt class="w-20 shrink-0 text-stone-400">{{ $label }}</dt>
                                    <dd class="text-stone-700">{{ $fabric->fabricType->sewing[$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @endif
            </x-card>

            <x-advanced-section title="جدول کامل مشخصات فیزیکی"
                description="همان اعدادی که موتور شبیه‌سازی و برش از آن‌ها استفاده می‌کند.">
                <div class="space-y-5">
                    @foreach ($groups as $groupKey => $groupLabel)
                        <div>
                            <p class="mb-2 text-sm font-bold text-stone-700">{{ $groupLabel }}</p>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-80 text-sm">
                                    <tbody class="divide-y divide-stone-100">
                                        @foreach ($schema as $key => $definition)
                                            @continue($definition['group'] !== $groupKey)

                                            <tr>
                                                <td class="py-2 text-stone-500">{{ $definition['label'] }}</td>
                                                <td class="py-2 text-end font-semibold text-stone-800">
                                                    @if ($definition['type'] === 'bool')
                                                        {{ $profile->get($key) ? 'بله' : 'خیر' }}
                                                    @elseif ($definition['type'] === 'option')
                                                        {{ $definition['options'][$profile->get($key)] ?? $profile->get($key) }}
                                                    @elseif ($definition['type'] === 'ratio')
                                                        {{ Format::ratio($profile->get($key)) }}
                                                    @else
                                                        {{ Jalali::digits((float) $profile->get($key)) }}
                                                        <span class="text-xs font-normal text-stone-400">
                                                            {{ $definition['unit'] }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <p class="mb-2 text-sm font-bold text-stone-700">پارامترهای موتور شبیه‌سازی</p>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-80 text-sm">
                                <tbody class="divide-y divide-stone-100">
                                    @foreach ($physics as $key => $value)
                                        <tr>
                                            <td class="py-1.5 font-mono text-xs text-stone-500" dir="ltr">{{ $key }}</td>
                                            <td class="py-1.5 text-end font-semibold text-stone-800" dir="ltr">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </x-advanced-section>
        </div>

        <div class="space-y-6">
            <x-card title="کدام لباس‌ها مناسب است؟" subtitle="نمره از مقایسه شناسنامه پارچه با سلیقه هر مدل می‌آید."
                icon="shirt">
                @if ($matches->isEmpty())
                    <p class="text-sm text-stone-500">هنوز مدل لباسی در سامانه ثبت نشده است.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($matches as $match)
                            @php($score = $match['score'])

                            <li>
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-stone-800">
                                        {{ $match['garment_type']->name_fa }}
                                    </span>
                                    <x-badge :color="match ($score['verdict']) {
                                        'suitable' => 'emerald',
                                        'acceptable' => 'amber',
                                        default => 'rose',
                                    }">
                                        {{ $score['verdict_label'] }}
                                    </x-badge>
                                </div>
                                <x-meter :value="$score['overall']" :max="100" :label="null" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($fabric->fabricType?->care)
                <x-card title="نگهداری" icon="info">
                    <dl class="space-y-2 text-sm">
                        @foreach (['wash' => 'شست‌وشو', 'iron' => 'اتو', 'dryer' => 'خشک‌کن'] as $key => $label)
                            @if (! empty($fabric->fabricType->care[$key]))
                                <div>
                                    <dt class="text-xs text-stone-400">{{ $label }}</dt>
                                    <dd class="text-stone-700">{{ $fabric->fabricType->care[$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </x-card>
            @endif

            <x-card title="آموزش این پارچه" icon="book">
                @if ($guides->isEmpty())
                    <p class="text-sm text-stone-500">
                        برای این پارچه راهنمای اختصاصی نداریم، اما
                        <a href="{{ route('guides.index') }}" class="font-semibold text-brand-600 hover:underline">
                            راهنماهای عمومی
                        </a>
                        را ببینید.
                    </p>
                @else
                    <ul class="space-y-2">
                        @foreach ($guides as $guide)
                            <li>
                                <a href="{{ route('guides.show', $guide) }}"
                                    class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm transition hover:bg-stone-50">
                                    <x-icon name="book" class="h-4 w-4 shrink-0 text-stone-400" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-stone-800">{{ $guide->title }}</span>
                                        @if ($guide->summary)
                                            <span class="block text-xs text-stone-500">{{ $guide->summary }}</span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($fabric->fabricType)
                <x-card>
                    <p class="text-sm text-stone-600">
                        این پارچه بر پایه «{{ $fabric->fabricType->name_fa }}» از بانک پارچه ساخته شده است.
                    </p>
                    <x-button href="{{ route('fabric-bank.show', $fabric->fabricType) }}" variant="soft" size="sm"
                        icon="palette" class="mt-3">دیدن پارچه پایه</x-button>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
