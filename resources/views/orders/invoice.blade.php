@use('App\Support\Format')
@use('App\Support\Jalali')

<x-print-layout :title="'فاکتور '.$order->code">
    {{-- سرصفحه کارگاه --}}
    <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-stone-800 pb-4">
        <div>
            <h1 class="text-2xl font-black">{{ $workshop?->name ?? config('app.name') }}</h1>
            <p class="mt-1 text-sm text-stone-600">
                @if ($workshop?->city)
                    {{ $workshop->city }}
                @endif
                @if ($workshop?->phone)
                    — تلفن {{ Jalali::digits($workshop->phone) }}
                @endif
            </p>
            @if ($workshop?->address)
                <p class="text-xs text-stone-500">{{ $workshop->address }}</p>
            @endif
        </div>

        <div class="text-end">
            <p class="text-lg font-bold">فاکتور فروش و خدمات</p>
            <p class="mt-1 text-sm">شماره: {{ Jalali::digits($order->code) }}</p>
            <p class="text-sm">تاریخ: {{ Jalali::date($order->created_at, true) }}</p>
        </div>
    </header>

    {{-- مشتری --}}
    <section class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
        <div class="rounded-xl border border-stone-200 p-4">
            <p class="text-xs text-stone-500">مشتری</p>
            <p class="mt-1 font-bold">{{ $order->customer?->name ?? '—' }}</p>
            @if ($order->customer?->phone)
                <p class="text-sm" dir="ltr">{{ Jalali::digits($order->customer->phone) }}</p>
            @endif
        </div>

        <div class="rounded-xl border border-stone-200 p-4">
            <p class="text-xs text-stone-500">شرح سفارش</p>
            <p class="mt-1 font-bold">{{ $order->title }}</p>
            <p class="text-sm text-stone-600">
                تاریخ تحویل: {{ Jalali::date($order->due_date, true) }} — وضعیت: {{ $order->statusLabel() }}
            </p>
        </div>
    </section>

    {{-- ردیف‌ها --}}
    <table class="mt-5 w-full border-collapse text-sm">
        <thead>
            <tr class="bg-stone-100 text-xs">
                <th class="border border-stone-300 px-2 py-2 text-start">#</th>
                <th class="border border-stone-300 px-2 py-2 text-start">شرح</th>
                <th class="border border-stone-300 px-2 py-2 text-start">مقدار</th>
                <th class="border border-stone-300 px-2 py-2 text-start">قیمت واحد</th>
                <th class="border border-stone-300 px-2 py-2 text-start">جمع</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td class="border border-stone-300 px-2 py-2">{{ Jalali::digits($loop->iteration) }}</td>
                    <td class="border border-stone-300 px-2 py-2">
                        {{ $item->description }}
                        <span class="text-xs text-stone-500">({{ $item->kindLabel() }})</span>
                    </td>
                    <td class="border border-stone-300 px-2 py-2 whitespace-nowrap">
                        {{ Format::number($item->quantity, 2) }} {{ $item->unit }}
                    </td>
                    <td class="border border-stone-300 px-2 py-2 whitespace-nowrap">
                        {{ Format::money($item->unit_price) }}
                    </td>
                    <td class="border border-stone-300 px-2 py-2 whitespace-nowrap">
                        {{ Format::money($item->total()) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="border border-stone-300 px-2 py-4 text-center text-stone-500">
                        ردیفی ثبت نشده است.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- جمع‌ها و پرداخت‌ها --}}
    <section class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-stone-200 p-4 text-sm">
            <p class="mb-2 font-bold">پرداخت‌ها</p>

            @if ((float) $order->deposit > 0)
                <div class="flex items-center justify-between py-1">
                    <span class="text-stone-600">بیعانه</span>
                    <span>{{ Format::money($order->deposit) }}</span>
                </div>
            @endif

            @forelse ($order->payments as $payment)
                <div class="flex items-center justify-between py-1">
                    <span class="text-stone-600">
                        {{ $payment->methodLabel() }} — {{ Jalali::date($payment->paid_at) }}
                    </span>
                    <span>{{ Format::money($payment->amount) }}</span>
                </div>
            @empty
                @if ((float) $order->deposit <= 0)
                    <p class="text-stone-500">پرداختی ثبت نشده است.</p>
                @endif
            @endforelse
        </div>

        <div class="rounded-xl border-2 border-stone-800 p-4 text-sm">
            <div class="flex items-center justify-between py-1">
                <span class="text-stone-600">جمع ردیف‌ها</span>
                <span>{{ Format::money($order->itemsTotal()) }}</span>
            </div>
            <div class="flex items-center justify-between py-1">
                <span class="text-stone-600">مبلغ کل سفارش</span>
                <span class="font-bold">{{ Format::money($order->price) }}</span>
            </div>
            <div class="flex items-center justify-between py-1">
                <span class="text-stone-600">پرداخت‌شده</span>
                <span>{{ Format::money($order->paidAmount()) }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between border-t border-stone-300 pt-2">
                <span class="font-bold">مانده قابل پرداخت</span>
                <span class="text-lg font-black">{{ Format::money($order->remainingAmount()) }}</span>
            </div>
        </div>
    </section>

    @if ($order->notes)
        <p class="mt-4 rounded-xl bg-stone-50 p-3 text-sm text-stone-700">{{ $order->notes }}</p>
    @endif

    {{-- امضا --}}
    <section class="mt-10 grid grid-cols-2 gap-8 text-sm">
        <div>
            <p class="text-stone-600">امضای کارگاه</p>
            <div class="mt-10 border-t border-dashed border-stone-400"></div>
        </div>
        <div>
            <p class="text-stone-600">امضای مشتری</p>
            <div class="mt-10 border-t border-dashed border-stone-400"></div>
        </div>
    </section>

    <p class="mt-6 text-center text-xs text-stone-400">
        این فاکتور در {{ Jalali::dateTime(now()) }} از سامانه {{ config('app.name') }} گرفته شده است.
    </p>
</x-print-layout>
