@php
    use App\Support\Format;
    use App\Support\Jalali;

    $fit = $data['fit'] ?? null;
    $quality = $data['quality'] ?? null;
@endphp

@if ($fit)
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <span class="font-semibold text-stone-800">
            امتیاز تناسب:
            {{ $fit['score'] !== null ? Jalali::digits((string) $fit['score']).' از ۱۰۰' : '—' }}
        </span>
        <span class="text-stone-500">حالت بدن: {{ $fit['pose'] ?? '—' }}</span>
        <span class="text-stone-400">{{ $fit['date_jalali'] ?? '' }}</span>
    </div>

    @if (! empty($fit['zones']))
        <div class="flex flex-wrap gap-2">
            @foreach ($fit['zones'] as $zone)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 px-2.5 py-1 text-xs">
                    <span class="h-2.5 w-2.5 rounded-full"
                        style="background: {{ $zone['color'] ?? '#a8a29e' }}"></span>
                    {{ $zone['label'] ?: $zone['key'] }}
                    @if (! empty($zone['level_label']))
                        <span class="text-stone-500">({{ $zone['level_label'] }})</span>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if (! empty($fit['warnings']))
        <ul class="mt-3 list-inside list-disc space-y-1 text-xs text-amber-800">
            @foreach ($fit['warnings'] as $warning)
                <li>{{ is_array($warning) ? ($warning['message'] ?? '') : $warning }}</li>
            @endforeach
        </ul>
    @endif
@else
    <p class="text-sm text-stone-500">تست تناسب روی مانکن انجام نشده است.</p>
@endif

@if ($quality)
    <div class="mt-5 border-t border-stone-100 pt-4">
        <p class="mb-2 text-sm font-bold text-stone-800">کارنامه کیفیت طراحی</p>

        @if (isset($quality['score']))
            <x-meter label="امتیاز کل" :value="(float) $quality['score']" />
        @endif

        <dl class="mt-3 space-y-1.5 text-sm">
            @foreach ($quality as $key => $value)
                @continue(in_array($key, ['score'], true) || is_array($value) && ! isset($value['label'], $value['value']))
                <div class="flex justify-between gap-3 border-b border-dotted border-stone-200 pb-1">
                    <dt class="text-stone-500">
                        {{ is_array($value) ? ($value['label'] ?? $key) : $key }}
                    </dt>
                    <dd class="font-medium text-stone-800">
                        @php $shown = is_array($value) ? ($value['value'] ?? '') : $value; @endphp
                        {{ is_numeric($shown) ? Format::number((float) $shown, 1) : $shown }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
