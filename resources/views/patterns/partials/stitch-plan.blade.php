{{--
    نقشهٔ دوخت.

    الگو می‌گوید هر لبه چیست و پارچه می‌گوید چه رفتاری دارد؛ از این دو، دستورِ
    دوختِ همان لبه درمی‌آید. چیزی این‌جا سلیقه‌ای نیست: فاصلهٔ کوک از وزنِ
    پارچه می‌آید، نوعِ درز از کشسانی و شفافیت و ریش‌شدنش، و جای دوخت از خودِ
    الگو.
--}}
@php($f = $stitchPlan['fabric'])

<x-card title="نقشهٔ دوخت" icon="scissors"
    subtitle="هر لبه با کدام کوک، چه فاصله‌ای و چه درزی — بر پایهٔ همین الگو و همین پارچه.">

    <div class="grid gap-3 rounded-2xl bg-stone-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="text-xs text-stone-500">پارچه</div>
            <div class="font-semibold text-stone-800">{{ $f['family_label'] }}</div>
            <div class="text-xs text-stone-500">{{ $f['weight'] }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">فاصلهٔ کوکِ درزدوزی</div>
            <div class="font-semibold text-stone-800">
                {{ \App\Support\Jalali::digits((string) $f['length']) }} میلی‌متر
            </div>
            <div class="text-xs text-stone-500">
                {{ \App\Support\Jalali::digits((string) $f['per_inch']) }} کوک در اینچ
            </div>
        </div>
        <div>
            <div class="text-xs text-stone-500">سوزن</div>
            <div class="font-semibold text-stone-800">{{ $f['needle'] }}</div>
            <div class="text-xs text-stone-500">{{ $f['needle_kind'] }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">نخ</div>
            <div class="font-semibold text-stone-800">{{ $f['thread'] }}</div>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[46rem] text-sm">
            <thead class="text-xs text-stone-500">
                <tr class="border-b border-stone-200">
                    <th class="py-2 text-start font-medium">لبه</th>
                    <th class="py-2 text-start font-medium">کوک</th>
                    <th class="py-2 text-start font-medium">فاصله</th>
                    <th class="py-2 text-start font-medium">درز و پاکدوزی</th>
                    <th class="py-2 text-start font-medium">جای دوخت</th>
                    <th class="py-2 text-start font-medium">طول کل</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($stitchPlan['edges'] as $edge)
                    <tr class="align-top">
                        <td class="py-3 font-semibold text-stone-800">
                            {{ $edge['name'] }}
                            @if ($edge['before'])
                                <div class="mt-1 text-xs font-normal text-brand-700">
                                    اول: {{ $edge['before']['name'] }}
                                </div>
                            @endif
                            @if ($edge['after'])
                                <div class="mt-1 text-xs font-normal text-brand-700">
                                    آخر: {{ $edge['after']['name'] }}
                                </div>
                            @endif
                        </td>
                        <td class="py-3 text-stone-700">{{ $edge['stitch_name'] }}</td>
                        <td class="py-3 whitespace-nowrap text-stone-700">
                            {{ \App\Support\Jalali::digits((string) $edge['length_mm']) }} م‌م
                            <div class="text-xs text-stone-400">
                                {{ \App\Support\Jalali::digits((string) $edge['per_inch']) }} در اینچ
                            </div>
                        </td>
                        <td class="py-3 text-stone-700">
                            <div class="font-medium">{{ $edge['seam_name'] }}</div>
                            <div class="mt-0.5 text-xs leading-5 text-stone-500">{{ $edge['finish'] }}</div>
                            @isset($edge['note'])
                                <div class="mt-1 text-xs leading-5 text-amber-700">{{ $edge['note'] }}</div>
                            @endisset
                        </td>
                        <td class="py-3 whitespace-nowrap text-stone-700">
                            {{ \App\Support\Format::cm($edge['allowance_cm']) }}
                        </td>
                        <td class="py-3 whitespace-nowrap text-stone-500">
                            {{ \App\Support\Format::cm($edge['total_cm']) }}
                        </td>
                    </tr>
                    @if ($edge['why'] || $edge['watch'])
                        <tr>
                            <td colspan="6" class="pb-3 text-xs leading-6 text-stone-500">
                                @if ($edge['why'])<span>{{ $edge['why'] }}</span>@endif
                                @if ($edge['watch'])
                                    <span class="text-amber-700"> {{ $edge['watch'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($stitchPlan['hand'] !== [])
        <h3 class="mt-6 text-sm font-semibold text-stone-800">کارِ دستی</h3>
        <p class="mt-1 text-xs text-stone-500">چرخ همه‌کار را نمی‌کند؛ این‌ها با دست بهتر درمی‌آیند.</p>

        <ul class="mt-2 space-y-2 text-sm">
            @foreach ($stitchPlan['hand'] as $hand)
                <li class="flex flex-wrap items-baseline gap-x-2 border-b border-dashed border-stone-200 pb-2">
                    <span class="font-semibold text-stone-800">{{ $hand['name'] }}</span>
                    <span class="text-xs text-stone-400">
                        {{ \App\Support\Jalali::digits((string) $hand['length_mm']) }} م‌م
                    </span>
                    <span class="text-stone-600">— {{ $hand['where'] }}</span>
                    <span class="w-full text-xs leading-5 text-stone-500">{{ $hand['why'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($stitchPlan['notes'] !== [])
        <ul class="mt-5 space-y-1.5 text-xs leading-6 text-stone-500">
            @foreach ($stitchPlan['notes'] as $note)
                <li>• {{ $note }}</li>
            @endforeach
        </ul>
    @endif
</x-card>
