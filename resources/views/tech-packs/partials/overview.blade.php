@php
    use App\Support\Format;
    use App\Support\Jalali;

    $garment = $data['garment'] ?? [];
    $fabric = $data['fabric'] ?? [];
    $cutting = $data['cutting'] ?? [];

    $rows = [
        'لباس' => $garment['name'] ?? '—',
        'نوع لباس' => $garment['type'] ?? '—',
        'دسته' => $garment['category'] ?? '—',
        'سایز' => $garment['size'] ? Jalali::digits((string) $garment['size']) : '—',
        'مشتری' => $garment['customer'] ?? '—',
        'کارگاه' => $garment['workshop'] ?? '—',
        'تاریخ' => $garment['date_jalali'] ?? '—',
    ];

    $fabricRows = [
        'پارچه' => $fabric['name'] ?? '—',
        'نوع پارچه' => $fabric['type'] ?? '—',
        'ترکیب الیاف' => $fabric['composition'] ?: '—',
        'عرض' => isset($fabric['width']) ? Format::cm($fabric['width']) : '—',
        'وزن' => isset($fabric['gsm']) ? Jalali::digits((string) $fabric['gsm']).' گرم بر متر مربع' : '—',
        'طرح سطح' => $fabric['surface_pattern'] ?? '—',
        'مصرف' => isset($fabric['consumption_m']) ? Jalali::digits(number_format((float) $fabric['consumption_m'], 2)).' متر' : '—',
        'هزینه پارچه' => $fabric['cost_label'] ?? '—',
        'آستر' => $fabric['lining'] ?? '—',
    ];

    $cuttingRows = [
        'عرض پارچه' => isset($cutting['width']) ? Format::cm($cutting['width']) : '—',
        'طول لازم' => isset($cutting['length']) ? Format::cm($cutting['length']) : '—',
        'دورریز' => isset($cutting['waste']) ? Format::percent($cutting['waste'], 1) : '—',
        'بهره‌وری' => isset($cutting['efficiency']) ? Format::percent($cutting['efficiency'], 1) : '—',
        'تای پارچه' => ($cutting['folded'] ?? true) ? 'تاشده از عرض' : 'باز',
        'وضعیت' => ($cutting['estimated'] ?? true) ? 'برآوردی (نقشه برش ذخیره نشده)' : 'از نقشه برش ذخیره‌شده',
    ];
@endphp

<div class="grid gap-6 md:grid-cols-3">
    @foreach ([['شناسنامه لباس', $rows], ['پارچه', $fabricRows], ['برش', $cuttingRows]] as [$title, $group])
        <div>
            <p class="mb-2 text-sm font-bold text-stone-800">{{ $title }}</p>
            <dl class="space-y-1.5 text-sm">
                @foreach ($group as $label => $value)
                    <div class="flex justify-between gap-3 border-b border-dotted border-stone-200 pb-1">
                        <dt class="shrink-0 text-stone-500">{{ $label }}</dt>
                        <dd class="text-end font-medium text-stone-800">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</div>

@if (! empty($cutting['warnings']))
    <ul class="mt-4 list-inside list-disc space-y-1 text-xs text-amber-800">
        @foreach ($cutting['warnings'] as $warning)
            <li>{{ $warning }}</li>
        @endforeach
    </ul>
@endif
