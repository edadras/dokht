{{--
    لباسِ دوخته‌شده از چهار طرف.

    نقشهٔ الگو را خیاط می‌خواند؛ این را مشتری هم می‌خواند. هر چهار تصویر از
    همین الگو و همین اندازه ساخته شده — نه از یک عکسِ آماده — پس اگر اندازهٔ
    مشتری عوض شود، شکل لباس هم عوض می‌شود.
--}}
<x-card title="لباس دوخته‌شده" icon="shirt"
    subtitle="از چهار طرف، با همین اندازه‌ها. خط دورِ هر نما از خودِ قطعه‌های الگو درمی‌آید.">

    @if (! $flats['ok'])
        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $flats['notes'][0] ?? 'نمای دوخت برای این مدل ساخته نشد.' }}
        </p>
    @else
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($flats['views'] as $key => $svg)
                <figure class="rounded-2xl border border-stone-200 bg-stone-50 p-3">
                    <div class="flex h-56 items-center justify-center overflow-hidden">
                        {!! $svg !!}
                    </div>
                    <figcaption class="mt-2 text-center text-xs font-semibold text-stone-600">
                        {{ \App\Services\Pattern\GarmentFlatService::VIEWS[$key] ?? $key }}
                    </figcaption>
                </figure>
            @endforeach
        </div>

        @if ($flats['measures'] !== [])
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($flats['measures'] as $label => $value)
                    <div class="flex items-baseline justify-between gap-2 border-b border-dashed border-stone-200 pb-1">
                        <dt class="text-stone-600">{{ $label }}</dt>
                        <dd class="font-semibold text-stone-900">{{ \App\Support\Format::cm($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <p class="mt-4 text-xs leading-6 text-stone-500">
            بلندی، پهنا و افتِ هر نما مستقیم از الگو خوانده شده است. تنها چیزی که
            اندازه‌گیری نیست، ضخامتِ لباس در نمای پهلوست: کاغذِ الگو تخت است و
            ضخامت ندارد، پس آن یکی از دورِ دوخته‌شدهٔ لباس و شکلِ مقطعِ تنه حساب
            می‌شود.
        </p>

        @foreach ($flats['notes'] as $note)
            <p class="mt-2 text-xs text-amber-700">{{ $note }}</p>
        @endforeach
    @endif
</x-card>

{{-- و بعد همان لباس، روی مانکن --}}
@if (($solid['ok'] ?? false))
    <x-card title="روی مانکن" icon="user"
        subtitle="همان لباس بالا، این بار دور بدنِ همین مشتری — با رنگ و جنس پارچه.">
        @include('patterns.partials.garment-solid', ['solid' => $solid])
    </x-card>
@endif
