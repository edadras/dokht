<x-app-layout :title="$pattern->name" wide>
    @php
        $digits = fn ($value) => \App\Support\Jalali::digits((string) $value);
    @endphp

    <x-page-header :title="$pattern->name" :back="route('patterns.index')"
        :subtitle="($pattern->garmentType?->name_fa ?? 'الگو').' • سایز '.$digits($pattern->base_size).' • نسخه '.$digits($pattern->version)">
        <x-slot:actions>
            <x-button href="{{ route('patterns.editor', $pattern) }}" icon="edit">ویرایشگر دوبعدی</x-button>
            <x-button href="{{ route('patterns.print', $pattern) }}" variant="secondary" icon="print" target="_blank">
                چاپ ۱:۱
            </x-button>
            <x-button href="{{ route('patterns.versions', $pattern) }}" variant="secondary" icon="clock">
                نسخه‌ها ({{ $digits($versionCount) }})
            </x-button>
            <x-button href="{{ route('patterns.edit', $pattern) }}" variant="secondary" icon="settings">تنظیم‌ها</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="قطعه‌ها" :value="$digits($pattern->pieces->count())" icon="layers" />
        <x-stat label="تعداد برش" :value="$digits($pattern->pieceCount())" icon="scissors" color="clay" />
        <x-stat label="مساحت قطعه‌ها" :value="\App\Support\Format::number($pattern->totalArea() / 10000, 2).' متر مربع'"
            icon="grid" color="sky" />
        <x-stat label="جای دوخت پیش‌فرض" :value="\App\Support\Format::cm($pattern->seamAllowance())" icon="ruler"
            color="emerald" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('patterns.partials.garment-flats', ['flats' => $flats])

            @include('patterns.partials.stitch-plan', ['stitchPlan' => $stitchPlan])

            <x-card title="نقشه الگو" icon="scissors" subtitle="خط پُر: خط دوخت — خط‌چین بنفش: خط برش با جای دوخت.">
                @include('patterns.partials.svg', ['svg' => $svg, 'height' => 'h-[32rem]'])
            </x-card>

            <x-card title="قطعه‌ها" icon="layers" padding="p-0">
                <div class="overflow-x-auto thin-scroll">
                    <table class="w-full text-sm">
                        <thead class="bg-stone-50 text-xs text-stone-500">
                            <tr>
                                <th class="p-3 text-start">قطعه</th>
                                <th class="p-3 text-start">لایه</th>
                                <th class="p-3 text-start">اندازه</th>
                                <th class="p-3 text-start">مساحت</th>
                                <th class="p-3 text-start">محیط</th>
                                <th class="p-3 text-start">برش</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($pattern->pieces as $piece)
                                <tr>
                                    <td class="p-3">
                                        <p class="font-semibold text-stone-800">{{ $piece->name }}</p>
                                        <p class="text-xs text-stone-400" dir="ltr">{{ $piece->code }}</p>
                                    </td>
                                    <td class="p-3 text-stone-600">{{ $piece->layerLabel() }}</td>
                                    <td class="p-3 text-stone-600">
                                        {{ $digits($piece->width()) }} × {{ $digits($piece->height()) }}
                                    </td>
                                    <td class="p-3 text-stone-600">{{ $digits(round($piece->area())) }} سانتی‌متر مربع</td>
                                    <td class="p-3 text-stone-600">{{ $digits(round($piece->perimeter())) }} سانتی‌متر</td>
                                    <td class="p-3">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <x-badge>{{ $digits($piece->cut_quantity) }} عدد</x-badge>
                                            @if ($piece->on_fold)
                                                <x-badge color="violet">روی تا</x-badge>
                                            @endif
                                            @if ($piece->mirror)
                                                <x-badge color="sky">قرینه</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="بررسی الگو" icon="check-circle"
                subtitle="همان بررسی‌هایی که روی الگوهای کاتالوگ اجرا می‌شود، روی این الگو هم اجرا شد.">
                <div class="flex items-center gap-3">
                    <span @class([
                        'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-bold',
                        'bg-emerald-50 text-emerald-700' => $inspection['errors'] === 0 && $inspection['warnings'] === 0,
                        'bg-amber-50 text-amber-700' => $inspection['errors'] === 0 && $inspection['warnings'] > 0,
                        'bg-rose-50 text-rose-700' => $inspection['errors'] > 0,
                    ])>{{ $digits($inspection['score']) }}</span>
                    <p class="text-sm text-stone-600">
                        @if ($inspection['errors'] > 0)
                            {{ $digits($inspection['errors']) }} ایراد جدی دارد؛ این الگو با این وضع بریده نمی‌شود.
                        @elseif ($inspection['warnings'] > 0)
                            بریدنی است، ولی {{ $digits($inspection['warnings']) }} نکته باید بررسی شود.
                        @else
                            هندسه و درزهای این الگو سالم است.
                        @endif
                    </p>
                </div>

                @if (! empty($inspection['findings']))
                    <ul class="mt-4 space-y-2">
                        @foreach ($inspection['findings'] as $finding)
                            <li @class([
                                'flex items-start gap-2 rounded-xl px-3 py-2 text-sm',
                                'bg-rose-50 text-rose-800' => $finding['level'] === 'error',
                                'bg-amber-50 text-amber-800' => $finding['level'] === 'warning',
                                'bg-stone-50 text-stone-600' => $finding['level'] === 'info',
                            ])>
                                <x-icon name="{{ $finding['level'] === 'info' ? 'info' : 'alert' }}" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ $finding['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="دوخت مجازی" icon="check-circle"
                subtitle="کدام لبه به کدام لبه دوخته می‌شود؛ نمای سه‌بعدی از همین فهرست استفاده می‌کند.">
                @if (empty($relations))
                    <p class="text-sm text-stone-500">برای این الگو رابطه دوختی پیشنهاد نشد.</p>
                @else
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($relations as $relation)
                            <li class="flex items-center gap-2 rounded-xl bg-stone-50 px-3 py-2 text-sm">
                                <x-icon name="refresh" class="h-4 w-4 shrink-0 text-brand-500" />
                                <span class="font-medium text-stone-700">{{ $relation['label'] ?? 'دوخت' }}</span>
                                <span class="ms-auto text-xs text-stone-500" dir="ltr">
                                    {{ $relation['from']['piece'] ?? '—' }}#{{ $digits($relation['from']['edge'] ?? 0) }}
                                    ↔
                                    {{ $relation['to']['piece'] ?? '—' }}#{{ $digits($relation['to']['edge'] ?? 0) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="جای دوخت" icon="ruler">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($seamSummary as $row)
                            <tr>
                                <td class="py-2 text-stone-600">{{ $row['label'] }}</td>
                                <td class="py-2 text-end font-semibold text-stone-800">
                                    {{ \App\Support\Format::cm($row['value']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-stone-500">لبه‌های روی تای پارچه جای دوخت نمی‌گیرند.</p>
            </x-card>

            <x-card title="اندازه‌ها و آزادی" icon="user"
                :subtitle="$pattern->measurementSet?->customer?->name ? 'از دفترچه '.$pattern->measurementSet->customer->name : 'از جدول سایز استاندارد'">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @foreach (['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'height'] as $key)
                        <div class="rounded-xl bg-stone-50 px-3 py-2">
                            <p class="text-xs text-stone-500">{{ \App\Support\Measurements::label($key) }}</p>
                            <p class="font-semibold">{{ $digits(data_get($pattern->measurements, $key, '—')) }}</p>
                        </div>
                    @endforeach
                </div>

                @if (! empty($pattern->ease))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($pattern->ease as $key => $value)
                            <x-badge color="amber">
                                آزادی {{ \App\Support\Measurements::label($key) }}: {{ $digits($value) }}
                            </x-badge>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card title="خروجی و اشتراک" icon="download">
                <div class="space-y-2">
                    <x-button href="{{ route('patterns.export', [$pattern, 'svg']) }}" variant="secondary" icon="download"
                        class="w-full">خروجی SVG</x-button>
                    <x-button href="{{ route('patterns.export', [$pattern, 'dxf']) }}" variant="secondary" icon="download"
                        class="w-full">خروجی DXF (کاتر و CAD)</x-button>
                    <x-button href="{{ route('patterns.export', [$pattern, 'json']) }}" variant="secondary" icon="download"
                        class="w-full">خروجی JSON</x-button>

                    <form method="POST" action="{{ route('patterns.duplicate', $pattern) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" icon="copy" class="w-full">رونوشت الگو</x-button>
                    </form>

                    <form method="POST" action="{{ route('patterns.publish', $pattern) }}">
                        @csrf
                        <x-button type="submit" :variant="$pattern->is_published ? 'soft' : 'primary'" icon="share"
                            class="w-full">
                            {{ $pattern->is_published ? 'لغو انتشار در کتابخانه' : 'انتشار در کتابخانه' }}
                        </x-button>
                    </form>
                </div>
            </x-card>

            <x-card title="سایزبندی" icon="chart" subtitle="از همین الگو، الگوی سایزهای دیگر ساخته می‌شود.">
                <form method="POST" action="{{ route('patterns.grade', $pattern) }}" class="space-y-3">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="sizes[]" value="{{ $size }}" class="peer sr-only"
                                    @disabled((string) $size === (string) $pattern->base_size)>
                                <span
                                    class="block rounded-xl border-2 border-stone-200 px-3 py-1.5 text-sm font-semibold text-stone-600 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-disabled:opacity-40">
                                    {{ $digits($size) }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <x-button type="submit" variant="secondary" icon="plus" class="w-full">ساخت الگوی سایزها</x-button>
                </form>
            </x-card>

            @if ($pattern->notes)
                <x-alert type="info" title="یادداشت">{{ $pattern->notes }}</x-alert>
            @endif

            <x-confirm-delete :action="route('patterns.destroy', $pattern)" label="حذف این الگو"
                message="الگو و همه قطعه‌هایش حذف می‌شود. مطمئن هستید؟" />
        </div>
    </div>
</x-app-layout>
