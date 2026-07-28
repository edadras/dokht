@use('App\Support\Format')
@use('App\Support\Jalali')

@php
    $totalOrders = max(1, (int) collect($statuses)->sum('count'));
@endphp

<x-app-layout title="گزارش‌ها" wide>
    <x-page-header title="گزارش‌ها" subtitle="عددهای واقعی کارگاه، بدون نمودار پیچیده." />

    @if (! $hasData)
        <x-empty-state icon="chart" title="هنوز داده‌ای برای گزارش نیست"
            description="وقتی چند سفارش و پرداخت ثبت کنید، اینجا روند کار کارگاه را می‌بینید.">
            <x-slot:action>
                <x-button href="{{ route('orders.create') }}" icon="plus">ثبت سفارش</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-6">
            {{-- سفارش و درآمد به تفکیک ماه شمسی --}}
            <x-card title="سفارش‌ها و درآمد شش ماه گذشته" icon="chart"
                subtitle="درآمد = بیعانه سفارش‌های همان ماه + پرداخت‌های ثبت‌شده در آن ماه.">
                <div class="flex items-end gap-3 overflow-x-auto pb-2">
                    @foreach ($months as $month)
                        @php
                            $orderHeight = (int) round(($month['orders'] / $maxOrders) * 100);
                            $revenueHeight = (int) round(($month['revenue'] / $maxRevenue) * 100);
                        @endphp

                        <div class="flex min-w-20 flex-1 flex-col items-center gap-2">
                            <div class="flex h-40 w-full items-end justify-center gap-1.5">
                                <div class="flex h-full w-1/2 items-end justify-center rounded-t-lg bg-stone-100"
                                    title="سفارش‌ها">
                                    <div class="w-full rounded-t-lg bg-brand-500 transition-all"
                                        style="height: {{ max($month['orders'] > 0 ? 4 : 0, $orderHeight) }}%"></div>
                                </div>
                                <div class="flex h-full w-1/2 items-end justify-center rounded-t-lg bg-stone-100"
                                    title="درآمد">
                                    <div class="w-full rounded-t-lg bg-emerald-500 transition-all"
                                        style="height: {{ max($month['revenue'] > 0 ? 4 : 0, $revenueHeight) }}%"></div>
                                </div>
                            </div>

                            <p class="text-center text-xs font-medium text-stone-600">{{ $month['label'] }}</p>
                            <p class="text-center text-[11px] text-stone-400">
                                {{ Jalali::digits($month['orders']) }} سفارش
                                <span class="block">{{ Format::money($month['revenue']) }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center justify-center gap-4 text-xs text-stone-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded bg-brand-500"></span> شمار سفارش
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded bg-emerald-500"></span> درآمد
                    </span>
                </div>
            </x-card>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- وضعیت سفارش‌ها --}}
                <x-card title="وضعیت سفارش‌ها" icon="grid">
                    <div class="space-y-3">
                        @foreach ($statuses as $row)
                            <div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-stone-700">{{ $row['label'] }}</span>
                                    <span class="font-semibold text-stone-800">
                                        {{ Jalali::digits($row['count']) }}
                                        <span class="text-stone-400">
                                            ({{ Format::percent(($row['count'] / $totalOrders) * 100) }})
                                        </span>
                                    </span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                                    <div class="h-full rounded-full bg-brand-500"
                                        style="width: {{ ($row['count'] / $totalOrders) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>

                {{-- میانگین زمان تحویل و مانده حساب‌ها --}}
                <div class="space-y-6">
                    <x-card title="میانگین زمان تحویل" icon="clock">
                        @if ($deliveryDays === null)
                            <p class="text-sm text-stone-500">هنوز سفارش تحویل‌شده‌ای ثبت نشده است.</p>
                        @else
                            <p class="text-3xl font-black text-stone-900">
                                {{ Format::number($deliveryDays, 1) }}
                                <span class="text-base font-medium text-stone-500">روز</span>
                            </p>
                            <p class="mt-1 text-xs text-stone-500">
                                از لحظه ثبت سفارش تا تحویل، میان همه سفارش‌های تحویل‌شده.
                            </p>
                        @endif
                    </x-card>

                    <x-card title="مانده حساب‌ها" icon="money">
                        <p class="text-2xl font-black text-rose-600">{{ Format::money($balances['total']) }}</p>
                        <p class="mt-1 text-xs text-stone-500">
                            در {{ Jalali::digits($balances['count']) }} سفارش هنوز طلب دارید.
                        </p>

                        @if ($balances['rows']->isNotEmpty())
                            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                                @foreach ($balances['rows'] as $order)
                                    <li class="flex items-center gap-2 py-2">
                                        <a href="{{ route('orders.show', $order) }}"
                                            class="min-w-0 flex-1 truncate hover:text-brand-600">
                                            {{ $order->customer?->name ?? 'بدون مشتری' }} — {{ $order->title }}
                                        </a>
                                        <span class="shrink-0 font-semibold">
                                            {{ Format::money($order->remainingAmount()) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- مشتری‌های برتر --}}
                <x-card title="مشتری‌های پرکار" icon="users" subtitle="بر پایه مجموع مبلغ سفارش‌ها.">
                    @if ($topCustomers->isEmpty())
                        <p class="text-sm text-stone-500">سفارشی به مشتری وصل نشده است.</p>
                    @else
                        @php($maxAmount = max(1, (float) $topCustomers->max('amount')))

                        <div class="space-y-3">
                            @foreach ($topCustomers as $row)
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <a href="{{ route('customers.show', $row['customer']) }}"
                                            class="truncate font-medium text-stone-700 hover:text-brand-600">
                                            {{ $row['customer']->name }}
                                        </a>
                                        <span class="shrink-0 text-stone-500">
                                            {{ Jalali::digits($row['orders']) }} سفارش •
                                            {{ Format::money($row['amount']) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                                        <div class="h-full rounded-full bg-clay-500"
                                            style="width: {{ ($row['amount'] / $maxAmount) * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>

                {{-- پارچه‌های پرمصرف --}}
                <x-card title="پارچه‌های پرمصرف" icon="layers" subtitle="از ردیف‌های پارچه در سفارش‌ها.">
                    @if ($topFabrics->isEmpty())
                        <p class="text-sm text-stone-500">ردیف پارچه‌ای در سفارش‌ها ثبت نشده است.</p>
                    @else
                        @php($maxMeters = max(0.01, (float) $topFabrics->max('meters')))

                        <div class="space-y-3">
                            @foreach ($topFabrics as $row)
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="truncate font-medium text-stone-700">{{ $row['name'] }}</span>
                                        <span class="shrink-0 text-stone-500">
                                            {{ Format::number($row['meters'], 1) }} متر •
                                            {{ Format::money($row['amount']) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                                        <div class="h-full rounded-full bg-sky-500"
                                            style="width: {{ ($row['meters'] / $maxMeters) * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</x-app-layout>
