@php
    use App\Support\Jalali;

    $pattern = $data['pattern'] ?? [];
    $pieces = $pattern['pieces'] ?? [];
@endphp

@if ($pieces)
    <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-stone-600">
        <span>الگو: <span class="font-semibold text-stone-800">{{ $pattern['name'] ?? '—' }}</span></span>
        <span>نسخه {{ Jalali::digits((string) ($pattern['version'] ?? 1)) }}</span>
        <span>{{ Jalali::digits((string) ($pattern['piece_count'] ?? 0)) }} قطعه برش</span>
        <span>مساحت کل {{ $pattern['total_area_label'] ?? '—' }}</span>
    </div>

    <div class="overflow-x-auto thin-scroll">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-stone-100 text-xs">
                    <th class="border border-stone-300 p-2 text-start">کد</th>
                    <th class="border border-stone-300 p-2 text-start">نام قطعه</th>
                    <th class="border border-stone-300 p-2 text-start">لایه</th>
                    <th class="border border-stone-300 p-2 text-start">تعداد</th>
                    <th class="border border-stone-300 p-2 text-start">عرض</th>
                    <th class="border border-stone-300 p-2 text-start">بلندی</th>
                    <th class="border border-stone-300 p-2 text-start">ساسون</th>
                    <th class="border border-stone-300 p-2 text-start">علامت</th>
                    <th class="border border-stone-300 p-2 text-start">نکته</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pieces as $piece)
                    <tr>
                        <td class="border border-stone-300 p-2 font-mono text-xs" dir="ltr">{{ $piece['code'] ?? '—' }}</td>
                        <td class="border border-stone-300 p-2">{{ $piece['name'] ?? '—' }}</td>
                        <td class="border border-stone-300 p-2">{{ $piece['layer_label'] ?? '—' }}</td>
                        <td class="border border-stone-300 p-2">{{ Jalali::digits((string) ($piece['cut_quantity'] ?? 1)) }}</td>
                        <td class="border border-stone-300 p-2">{{ Jalali::digits(number_format((float) ($piece['width'] ?? 0), 1)) }}</td>
                        <td class="border border-stone-300 p-2">{{ Jalali::digits(number_format((float) ($piece['height'] ?? 0), 1)) }}</td>
                        <td class="border border-stone-300 p-2">{{ Jalali::digits((string) ($piece['darts'] ?? 0)) }}</td>
                        <td class="border border-stone-300 p-2">{{ Jalali::digits((string) ($piece['notches'] ?? 0)) }}</td>
                        <td class="border border-stone-300 p-2 text-xs">
                            {{ collect([
                                ! empty($piece['on_fold']) ? 'روی تا' : null,
                                ! empty($piece['mirror']) ? 'قرینه' : null,
                            ])->filter()->implode(' • ') ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (! empty($pattern['seam_allowances']))
        <div class="mt-4">
            <p class="mb-2 text-sm font-bold text-stone-800">جای دوخت</p>
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach ($pattern['seam_allowances'] as $allowance)
                    <span class="rounded-full bg-stone-100 px-2.5 py-1">
                        {{ $allowance['label'] }}:
                        {{ Jalali::digits(number_format((float) $allowance['value'], 1)) }} سانتی‌متر
                    </span>
                @endforeach
            </div>
        </div>
    @endif
@else
    <p class="text-sm text-stone-500">هنوز الگویی با قطعه‌های مشخص برای این پروژه ساخته نشده است.</p>
@endif
