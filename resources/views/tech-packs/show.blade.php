@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-app-layout title="کارت فنی" wide>
    <x-page-header title="کارت فنی" :back="route('projects.show', $project)"
        :subtitle="$techPack
            ? 'نسخه '.Jalali::digits((string) $techPack->version).' • '.Jalali::dateTime($techPack->created_at)
            : 'این کارت فنی هنوز ذخیره نشده است؛ پیش‌نمایش زیر از داده‌های امروز ساخته شده.'">
        <x-slot:actions>
            <form method="POST" action="{{ route('tech-packs.store', $project) }}">
                @csrf
                <x-button type="submit" icon="refresh">
                    {{ $techPack ? 'ساخت نسخه تازه' : 'ذخیره کارت فنی' }}
                </x-button>
            </form>

            <x-button variant="secondary" icon="print" href="{{ route('tech-packs.print', $project) }}"
                target="_blank">چاپ</x-button>

            <x-button variant="soft" icon="download" href="{{ route('projects.export', $project) }}">
                بسته خروجی (زیپ)
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-step-nav :project="$project" current="10" />

    @if (! empty($data['missing']))
        <x-alert type="warning" title="برای کامل شدن کارت فنی این موارد مانده است" class="mb-6">
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($data['missing'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </x-alert>
    @else
        <x-alert type="success" title="کارت فنی کامل است" class="mb-6">
            همه بخش‌ها پر شده‌اند؛ می‌توانید چاپ بگیرید یا بسته خروجی را دانلود کنید.
        </x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="مصرف پارچه" icon="layers"
            :value="Jalali::digits(number_format((float) ($data['cutting']['meters'] ?? 0), 2)).' متر'"
            :hint="($data['cutting']['estimated'] ?? true) ? 'برآوردی' : 'از نقشه برش'" />

        <x-stat label="شمار قطعه" icon="grid" color="sky"
            :value="Jalali::digits((string) ($data['pattern']['piece_count'] ?? 0))"
            :hint="$data['pattern']['name'] ?? 'الگو ساخته نشده'" />

        <x-stat label="هزینه مواد" icon="money" color="emerald"
            :value="$data['bom']['total_label'] ?? Format::money($data['bom']['total'] ?? 0)"
            :hint="($data['bom']['from_order'] ?? false) ? 'از سفارش' : 'برآوردی'" />

        <x-stat label="امتیاز تناسب" icon="check-circle" color="amber"
            :value="isset($data['fit']['score']) ? Jalali::digits((string) $data['fit']['score']) : '—'"
            :hint="$data['fit']['pose'] ?? 'تست تناسب انجام نشده'" />
    </div>

    <div class="mt-6 space-y-6">
        @if ($thumbnails)
            <x-card title="نمای قطعه‌ها" icon="shirt" subtitle="هندسه الگو، همان که روی پارچه بریده می‌شود">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($thumbnails as $thumbnail)
                        <div class="rounded-xl border border-stone-200 p-3">
                            <p class="mb-2 text-sm font-semibold text-stone-700">{{ $thumbnail['label'] }}</p>
                            <div class="rounded-lg bg-stone-50 p-2">{!! $thumbnail['svg'] !!}</div>
                            <p class="mt-2 text-xs text-stone-500">
                                {{ Jalali::digits((string) $thumbnail['piece_count']) }} قطعه برش
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

        <x-card title="خلاصه فنی" icon="clipboard">
            @include('tech-packs.partials.overview', ['data' => $data])
        </x-card>

        <x-card title="قطعه‌های الگو" icon="scissors">
            @include('tech-packs.partials.pieces', ['data' => $data])
        </x-card>

        <x-card title="برگه اندازه" icon="ruler">
            @include('tech-packs.partials.measurements', ['data' => $data])
        </x-card>

        @if ($plan)
            <x-card title="نقشه برش" icon="grid" :subtitle="$layout?->summaryText()">
                <div class="overflow-x-auto thin-scroll">
                    <div class="min-w-[320px]">{!! $plan !!}</div>
                </div>

                <div class="mt-3">
                    <x-button variant="secondary" size="sm" icon="eye"
                        href="{{ route('cutting-layouts.show', $layout) }}">صفحه نقشه برش</x-button>
                </div>
            </x-card>
        @else
            <x-card title="نقشه برش" icon="grid">
                <x-empty-state icon="scissors" title="نقشه برش ساخته نشده"
                    description="با یک دکمه می‌توانید بدانید چند متر پارچه لازم است و نقشه چیدمان را چاپ کنید.">
                    <x-slot:action>
                        <form method="POST" action="{{ route('projects.cutting', $project) }}">
                            @csrf
                            <x-button type="submit" icon="scissors">ساخت نقشه برش</x-button>
                        </form>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        @endif

        <x-card title="ترتیب دوخت" icon="book">
            @include('tech-packs.partials.construction', ['data' => $data])
        </x-card>

        <x-card title="مواد و متعلقات" icon="box">
            @include('tech-packs.partials.materials', ['data' => $data])
        </x-card>

        <x-card title="تناسب و کیفیت" icon="check-circle">
            @include('tech-packs.partials.fit', ['data' => $data])
        </x-card>

        @if (! empty($data['notes']))
            <x-card title="یادداشت‌ها" icon="file">
                <ul class="list-inside list-disc space-y-1 text-sm text-stone-700">
                    @foreach ($data['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card title="خروجی‌ها و نسخه‌ها" icon="download">
            <div class="flex flex-wrap gap-2">
                <x-button variant="secondary" size="sm" icon="download"
                    href="{{ route('projects.export', $project) }}">همه فایل‌ها (زیپ)</x-button>

                @foreach ([
                    'svg' => 'الگو (SVG)',
                    'dxf' => 'الگو (DXF)',
                    'json' => 'کارت فنی (JSON)',
                    'csv' => 'برگه اندازه (CSV)',
                ] as $format => $label)
                    <x-button variant="ghost" size="sm"
                        href="{{ route('projects.export', [$project, 'format' => $format]) }}">{{ $label }}</x-button>
                @endforeach
            </div>

            @if ($versions->isNotEmpty())
                <ul class="mt-4 divide-y divide-stone-100 text-sm">
                    @foreach ($versions as $version)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>
                                نسخه {{ Jalali::digits((string) $version->version) }}
                                <span class="text-xs text-stone-500">{{ Jalali::dateTime($version->created_at) }}</span>
                            </span>

                            @if ($version->isComplete())
                                <x-badge color="emerald" icon="check">کامل</x-badge>
                            @else
                                <x-badge color="amber">
                                    {{ Jalali::digits((string) count($version->missing())) }} مورد ناقص
                                </x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
