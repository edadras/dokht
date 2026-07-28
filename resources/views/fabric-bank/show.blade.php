@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-app-layout :title="$type->name_fa">
    <x-page-header :title="$type->name_fa" :subtitle="$type->name_en" :back="route('fabric-bank.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="این پارچه چیست؟" icon="palette">
                <p class="text-sm leading-7 text-stone-700">{{ $type->description }}</p>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <x-badge color="clay">{{ $type->categoryLabel() }}</x-badge>
                    @if ($type->familyLabel())
                        <x-badge color="violet">{{ $type->familyLabel() }}</x-badge>
                    @endif
                    <x-badge color="brand">{{ $profile->drapeLabel() }}</x-badge>
                    <x-badge>{{ $profile->weightLabel() }}</x-badge>
                    <x-badge>{{ $profile->thicknessLabel() }}</x-badge>
                </div>

                @if ($type->compositionLabel())
                    <p class="mt-4 text-sm text-stone-600">ترکیب الیاف: {{ $type->compositionLabel() }}</p>
                @endif

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <x-meter label="لختی" :value="$profile->get('drape')" :max="1" />
                    <x-meter label="سفتی" :value="$profile->get('stiffness')" :max="1" />
                    <x-meter label="کشسانی عرض" :value="$profile->get('stretch_weft')" :max="100" />
                    <x-meter label="شفافیت" :value="$profile->get('transparency')" :max="1" />
                    <x-meter label="براقی" :value="$profile->get('sheen')" :max="1" />
                    <x-meter label="سختی کار" :value="$profile->difficulty()" :max="1" />
                </div>
            </x-card>

            <div class="grid gap-6 sm:grid-cols-2">
                <x-card title="نگهداری" icon="info">
                    <dl class="space-y-2 text-sm">
                        @foreach (['wash' => 'شست‌وشو', 'iron' => 'اتو', 'dryer' => 'خشک‌کن'] as $key => $label)
                            <div>
                                <dt class="text-xs text-stone-400">{{ $label }}</dt>
                                <dd class="text-stone-700">{{ $type->care[$key] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card title="پیشنهاد دوخت" icon="scissors">
                    <dl class="space-y-2 text-sm">
                        @foreach (['needle' => 'سوزن', 'stitch' => 'کوک', 'seam' => 'درزبندی', 'tip' => 'نکته'] as $key => $label)
                            <div>
                                <dt class="text-xs text-stone-400">{{ $label }}</dt>
                                <dd class="text-stone-700">{{ $type->sewing[$key] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            </div>

            <x-advanced-section title="جدول کامل مشخصات فیزیکی"
                description="همان اعدادی که موتور قواعد و شبیه‌سازی با آن‌ها کار می‌کند.">
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
            <x-card title="افزودن به انبار من" subtitle="نام و رنگ اختیاری است." icon="plus">
                <form method="POST" action="{{ route('fabric-bank.add', $type) }}" class="space-y-4">
                    @csrf

                    <x-field label="نام" name="name" :hint="'خالی بگذارید تا «'.$type->name_fa.'» گذاشته شود.'">
                        <x-input name="name" placeholder="{{ $type->name_fa }}" />
                    </x-field>

                    <x-field label="رنگ" name="color">
                        <div class="flex gap-2">
                            <x-input name="color" placeholder="مثلاً مشکی" class="flex-1" />
                            <input type="color" name="color_hex" value="#8b5e3c"
                                class="h-11 w-14 shrink-0 cursor-pointer rounded-xl border border-stone-300 bg-white p-1"
                                aria-label="انتخاب رنگ">
                        </div>
                    </x-field>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="عرض" name="width_cm" suffix="سانتی‌متر">
                            <x-input type="number" name="width_cm" step="1" min="30" max="400" placeholder="۱۴۰" />
                        </x-field>

                        <x-field label="موجودی" name="stock_meters" suffix="متر">
                            <x-input type="number" name="stock_meters" step="0.1" min="0" placeholder="۰" />
                        </x-field>
                    </div>

                    <x-button type="submit" icon="plus" class="w-full">افزودن به پارچه‌های من</x-button>
                </form>
            </x-card>

            <x-card title="برای چه لباس‌هایی خوب است؟" icon="shirt">
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
                                        {{ Format::number($score['overall']) }}
                                    </x-badge>
                                </div>
                                <x-meter :value="$score['overall']" :max="100" :label="null" :show-value="false" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($guides->isNotEmpty())
                <x-card title="آموزش" icon="book">
                    <ul class="space-y-2">
                        @foreach ($guides as $guide)
                            <li>
                                <a href="{{ route('guides.show', $guide) }}"
                                    class="block rounded-xl px-2 py-2 text-sm font-semibold text-stone-800 transition hover:bg-stone-50">
                                    {{ $guide->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($myFabrics->isNotEmpty())
                <x-card title="پارچه‌های من از این نوع" icon="layers">
                    <ul class="space-y-2">
                        @foreach ($myFabrics as $fabric)
                            <li>
                                <a href="{{ route('fabrics.show', $fabric) }}"
                                    class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm transition hover:bg-stone-50">
                                    @include('fabrics.partials.swatch', ['fabric' => $fabric, 'size' => 'h-6 w-6'])
                                    <span class="min-w-0 flex-1 truncate font-semibold text-stone-800">
                                        {{ $fabric->displayName() }}
                                    </span>
                                    <span class="shrink-0 text-xs text-stone-500">
                                        {{ Format::number($fabric->stock_meters, 1) }} متر
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
