@php
    use App\Support\Format;
    use App\Support\Jalali;
@endphp

<x-print-layout title="نقشه برش">
    <header class="mb-6 border-b border-stone-300 pb-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-black">نقشه برش پارچه</h1>
                <p class="mt-1 text-sm text-stone-600">
                    {{ $project?->name ?? 'بدون پروژه' }}
                    @if ($project?->customer)
                        — مشتری: {{ $project->customer->name }}
                    @endif
                </p>
            </div>

            <div class="text-end text-xs text-stone-600">
                <p>{{ auth()->user()->workshop?->name }}</p>
                <p>{{ Jalali::date(now(), true) }}</p>
            </div>
        </div>
    </header>

    <section class="mb-6 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        @foreach ([
            'پارچه لازم' => Jalali::digits(number_format($layout->requiredMeters(), 2)).' متر',
            'عرض پارچه' => Format::cm($layout->fabric_width_cm).($layout->folded ? ' (تاشده)' : ' (باز)'),
            'دورریز' => Format::percent($layout->waste_percent, 1),
            'شمار قطعه' => Jalali::digits((string) $layout->placementCount()),
        ] as $label => $value)
            <div class="rounded-lg border border-stone-300 p-3">
                <p class="text-xs text-stone-500">{{ $label }}</p>
                <p class="font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="mb-6">
        <div class="rounded-lg border border-stone-300 p-3">
            {!! $plan !!}
        </div>
        <p class="mt-2 text-xs text-stone-500">
            پارچه {{ $layout->folded ? 'از عرض تا شده؛ لبه تا سمت راست نقشه است' : 'باز پهن شده است' }}.
            خط‌چین داخل قطعه‌ها خط دوخت و خط بیرونی خط برش است.
        </p>
    </section>

    <section class="mb-6">
        <h2 class="mb-2 font-bold">فهرست قطعه‌ها</h2>
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-stone-100 text-xs">
                    <th class="border border-stone-300 p-2 text-start">قطعه</th>
                    <th class="border border-stone-300 p-2 text-start">لایه</th>
                    <th class="border border-stone-300 p-2 text-start">برش</th>
                    <th class="border border-stone-300 p-2 text-start">اندازه (سانتی‌متر)</th>
                    <th class="border border-stone-300 p-2 text-start">وضعیت</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($layout->placements ?? [] as $placement)
                    <tr>
                        <td class="border border-stone-300 p-2">
                            {{ $placement['name'] ?? $placement['code'] ?? '—' }}
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ \App\Models\PatternPiece::LAYERS[$placement['layer'] ?? 'outer'] ?? ($placement['layer'] ?? '—') }}
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ Jalali::digits((string) ($placement['cut_index'] ?? 1)) }} از
                            {{ Jalali::digits((string) ($placement['cut_total'] ?? 1)) }}
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ Jalali::digits(number_format($placement['width'] ?? 0, 1)) }} ×
                            {{ Jalali::digits(number_format($placement['height'] ?? 0, 1)) }}
                        </td>
                        <td class="border border-stone-300 p-2 text-xs">
                            {{ collect([
                                ! empty($placement['on_fold']) ? 'روی تا' : null,
                                ! empty($placement['mirrored']) ? 'قرینه' : null,
                                (int) ($placement['rotation'] ?? 0) !== 0 ? 'چرخش '.Jalali::digits((string) $placement['rotation']).'°' : null,
                            ])->filter()->implode(' • ') ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if ($layout->warnings)
        <section>
            <h2 class="mb-2 font-bold">نکته‌های برش</h2>
            <ul class="list-inside list-disc space-y-1 text-sm text-stone-700">
                @foreach ($layout->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</x-print-layout>
