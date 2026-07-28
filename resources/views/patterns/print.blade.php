<x-print-layout :title="'چاپ الگوی '.$pattern->name">
    @php
        $digits = fn ($value) => \App\Support\Jalali::digits((string) $value);
    @endphp

    {{-- چاپ باید بدون تغییر مقیاس انجام شود، پس حاشیه صفحه در چاپ برداشته می‌شود --}}
    <style>
        @media print {
            body > div:last-child {
                max-width: none;
                padding-inline: 0;
            }
        }
    </style>

    {{-- برگ راهنما --}}
    <div class="print-page mb-8 border-b border-dashed border-stone-300 pb-8">
        <h1 class="text-xl font-black">{{ $pattern->name }}</h1>
        <p class="mt-1 text-sm text-stone-600">
            {{ $pattern->garmentType?->name_fa ?? 'الگو' }} • سایز {{ $digits($pattern->base_size) }} •
            نسخه {{ $digits($pattern->version) }} • {{ \App\Support\Jalali::date(now(), true) }}
        </p>

        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-bold">چند صفحه‌ای است — قبل از برش، مقیاس را کنترل کنید</p>
            <ol class="mt-2 list-inside list-decimal space-y-1">
                <li>در پنجره چاپ، اندازه صفحه را A4 و مقیاس را «۱۰۰٪» یا «اندازه واقعی» بگذارید (بدون «برازش با صفحه»).</li>
                <li>روی هر برگ یک خط‌کش ۱۰ سانتی‌متری چاپ می‌شود؛ با خط‌کش واقعی اندازه‌اش را بسنجید.</li>
                <li>برگ‌ها را از روی علامت‌های ضربدری گوشه‌ها روی هم بگذارید و بچسبانید؛ یک سانتی‌متر هم‌پوشانی دارند.</li>
                <li>شماره «ستون» و «سطر» بالای هر برگ می‌گوید هر برگ کجای قطعه است.</li>
            </ol>
        </div>

        <table class="mt-5 w-full text-sm">
            <thead class="bg-stone-100 text-xs text-stone-600">
                <tr>
                    <th class="p-2 text-start">قطعه</th>
                    <th class="p-2 text-start">اندازه (سانتی‌متر)</th>
                    <th class="p-2 text-start">تعداد برش</th>
                    <th class="p-2 text-start">روی تای پارچه</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @foreach ($pattern->pieces as $piece)
                    <tr>
                        <td class="p-2 font-semibold">{{ $piece->name }}</td>
                        <td class="p-2">{{ $digits($piece->width()) }} × {{ $digits($piece->height()) }}</td>
                        <td class="p-2">{{ $digits($piece->cut_quantity) }}</td>
                        <td class="p-2">{{ $piece->on_fold ? 'بله' : 'خیر' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-4 text-xs text-stone-500">
            {{ $withSeam ? 'خط‌چین بیرونی خط برش (با جای دوخت) و خط پُر خط دوخت است.' : 'فقط خط دوخت چاپ شده است.' }}
            در مجموع {{ $digits(count($pages)) }} برگ الگو.
        </p>
    </div>

    {{-- برگ‌های الگو در اندازه واقعی --}}
    @foreach ($pages as $page)
        <div class="print-page mb-8">
            <p class="mb-1 text-xs text-stone-500 no-print">{{ $page['label'] }}</p>
            {{-- بدون هیچ محدودیت اندازه؛ ابعاد SVG در میلی‌متر است و باید دست‌نخورده چاپ شود --}}
            <div class="overflow-x-auto">
                {!! $page['svg'] !!}
            </div>
        </div>
    @endforeach
</x-print-layout>
