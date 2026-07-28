@php
    use App\Support\Format;
    use App\Support\Jalali;

    $materials = $data['materials'] ?? [];
    $bom = $data['bom'] ?? [];
@endphp

@if ($materials)
    <div class="mb-5 grid gap-2 sm:grid-cols-2">
        @foreach ($materials as $material)
            <div class="flex items-start gap-2 rounded-xl border border-stone-200 p-3 text-sm">
                <span class="shrink-0 font-semibold text-stone-700">{{ $material['label'] }}:</span>
                <span class="text-stone-600">{{ $material['value'] }}</span>
            </div>
        @endforeach
    </div>
@endif

@if (! empty($bom['rows']))
    <p class="mb-2 text-sm font-bold text-stone-800">
        صورت مواد
        <span class="text-xs font-normal text-stone-500">
            ({{ ($bom['from_order'] ?? false) ? 'از ردیف‌های سفارش '.($bom['order_code'] ?? '') : 'برآوردی' }})
        </span>
    </p>

    <div class="overflow-x-auto thin-scroll">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-stone-100 text-xs">
                    <th class="border border-stone-300 p-2 text-start">گروه</th>
                    <th class="border border-stone-300 p-2 text-start">شرح</th>
                    <th class="border border-stone-300 p-2 text-start">مقدار</th>
                    <th class="border border-stone-300 p-2 text-start">قیمت واحد</th>
                    <th class="border border-stone-300 p-2 text-start">جمع</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bom['rows'] as $row)
                    <tr>
                        <td class="border border-stone-300 p-2">{{ $row['kind_label'] ?? '—' }}</td>
                        <td class="border border-stone-300 p-2">
                            {{ $row['label'] ?? '—' }}
                            @if (! empty($row['description']))
                                <span class="block text-xs text-stone-500">{{ $row['description'] }}</span>
                            @endif
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ Jalali::digits((string) ($row['quantity'] ?? 0)) }} {{ $row['unit'] ?? '' }}
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ ($row['unit_price'] ?? 0) > 0 ? Format::money($row['unit_price']) : '—' }}
                        </td>
                        <td class="border border-stone-300 p-2">
                            {{ ($row['total'] ?? 0) > 0 ? Format::money($row['total']) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-stone-50 font-bold">
                    <td class="border border-stone-300 p-2" colspan="4">جمع کل</td>
                    <td class="border border-stone-300 p-2">{{ $bom['total_label'] ?? Format::money($bom['total'] ?? 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if (! empty($bom['notes']))
        <ul class="mt-3 list-inside list-disc space-y-1 text-xs text-stone-500">
            @foreach ($bom['notes'] as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    @endif
@endif
