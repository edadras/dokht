@use('App\Models\Order')
@use('App\Support\Format')
@use('App\Support\Jalali')

@php
    $sortLink = fn (string $column) => route(
        'orders.index',
        array_merge(request()->query(), [
            'view' => 'list',
            'sort' => $column,
            'dir' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ]),
    );
@endphp

<x-app-layout title="فهرست سفارش‌ها" wide>
    <x-page-header title="فهرست سفارش‌ها" subtitle="پالایه بگذارید و ستون‌ها را مرتب کنید.">
        <x-slot:actions>
            <div class="flex items-center gap-1 rounded-xl border border-stone-200 bg-white p-1">
                <a href="{{ route('orders.index') }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-stone-600 hover:bg-stone-100">تخته</a>
                <a href="{{ route('orders.index', ['view' => 'list']) }}"
                    class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">فهرست</a>
            </div>
            <x-button href="{{ route('orders.create') }}" icon="plus">سفارش جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('orders.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="hidden" name="view" value="list">

        <x-field label="جست‌وجو">
            <x-input name="q" :value="request('q')" placeholder="عنوان، کد یا مشتری" />
        </x-field>

        <x-field label="وضعیت">
            <x-select name="status" :selected="request('status')" placeholder="همه"
                :options="collect(Order::STATUSES)->map(fn ($meta) => $meta['label'])->all()" />
        </x-field>

        <x-field label="مشتری">
            <x-select name="customer" :selected="request('customer')" placeholder="همه" :options="$customers" />
        </x-field>

        <x-field label="فقط دیرکردها">
            <x-select name="overdue" :selected="request('overdue')" :options="['' => 'همه سفارش‌ها', '1' => 'فقط دیرکردها']" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" variant="secondary" icon="search">اعمال</x-button>
            <x-button href="{{ route('orders.index', ['view' => 'list']) }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($orders->isEmpty())
        <x-empty-state icon="clipboard" title="سفارشی با این پالایه پیدا نشد"
            description="پالایه‌ها را ساده‌تر کنید یا سفارش تازه‌ای ثبت کنید." />
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start">
                                <a href="{{ $sortLink('code') }}" class="hover:text-brand-600">کد</a>
                            </th>
                            <th class="px-4 py-3 text-start">عنوان و مشتری</th>
                            <th class="px-4 py-3 text-start">
                                <a href="{{ $sortLink('status') }}" class="hover:text-brand-600">وضعیت</a>
                            </th>
                            <th class="px-4 py-3 text-start">
                                <a href="{{ $sortLink('due_date') }}" class="hover:text-brand-600">تحویل</a>
                            </th>
                            <th class="px-4 py-3 text-start">
                                <a href="{{ $sortLink('price') }}" class="hover:text-brand-600">مبلغ</a>
                            </th>
                            <th class="px-4 py-3 text-start">مانده</th>
                            <th class="px-4 py-3 text-start">مسئول</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-stone-50">
                                <td class="px-4 py-3 whitespace-nowrap text-stone-500">
                                    {{ Jalali::digits($order->code) }}
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('orders.show', $order) }}"
                                        class="font-semibold hover:text-brand-600">{{ $order->title }}</a>
                                    <p class="text-xs text-stone-500">{{ $order->customer?->name ?? 'بدون مشتری' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$order->statusColor()">{{ $order->statusLabel() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ Jalali::date($order->due_date) }}
                                    @if ($order->isOverdue())
                                        <span class="block text-xs font-semibold text-rose-600">دیرکرد</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ Format::money($order->price) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="{{ $order->remainingAmount() > 0 ? 'font-semibold text-rose-600' : 'text-stone-500' }}">
                                        {{ Format::money($order->remainingAmount()) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-stone-600">
                                    {{ $order->assignee?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-button href="{{ route('orders.show', $order) }}" size="sm" variant="ghost"
                                        icon="eye">دیدن</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-app-layout>
