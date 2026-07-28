@php
    use App\Support\Jalali;

    $sheet = $data['measurements'] ?? [];
    $rows = collect($sheet['rows'] ?? [])->filter(fn ($row) => $row['value'] !== null);
    $essential = $rows->filter(fn ($row) => $row['essential']);
    $optional = $rows->reject(fn ($row) => $row['essential']);
@endphp

<div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-stone-600">
    <span>منبع: {{ $sheet['source_label'] ?? '—' }}</span>
    @if (! empty($sheet['size']))
        <span>سایز {{ Jalali::digits((string) $sheet['size']) }}</span>
    @endif
    <span>واحد: سانتی‌متر</span>
</div>

<div class="grid gap-6 md:grid-cols-2">
    @foreach ([['اندازه‌های اصلی', $essential], ['اندازه‌های تکمیلی', $optional]] as [$title, $group])
        @if ($group->isNotEmpty())
            <div>
                <p class="mb-2 text-sm font-bold text-stone-800">{{ $title }}</p>
                <table class="w-full border-collapse text-sm">
                    <tbody>
                        @foreach ($group as $row)
                            <tr>
                                <td class="border border-stone-200 p-2 text-stone-600">{{ $row['label'] }}</td>
                                <td class="border border-stone-200 p-2 font-semibold">
                                    {{ Jalali::digits(number_format((float) $row['value'], 1)) }}
                                </td>
                                <td class="border border-stone-200 p-2 text-xs text-stone-400">
                                    {{ $row['derived'] ? 'تخمینی' : 'اندازه‌گیری‌شده' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
</div>

@if (! empty($sheet['ease']))
    <div class="mt-4">
        <p class="mb-2 text-sm font-bold text-stone-800">آزادی (اضافه راحتی)</p>
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach ($sheet['ease'] as $ease)
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-brand-700">
                    {{ $ease['label'] }}: {{ Jalali::digits(number_format((float) $ease['value'], 1)) }} سانتی‌متر
                </span>
            @endforeach
        </div>
    </div>
@endif
