@use('App\Models\Order')
@use('App\Support\Format')
@use('App\Support\Jalali')

@php
    $statusOptions = collect(Order::STATUSES)->map(fn ($meta) => $meta['label'])->all();
@endphp

<x-app-layout title="سفارش‌ها" wide>
    <x-page-header title="تخته تولید" subtitle="کارت هر سفارش را با کشیدن به ستون بعدی ببرید.">
        <x-slot:actions>
            <div class="flex items-center gap-1 rounded-xl border border-stone-200 bg-white p-1">
                <a href="{{ route('orders.index') }}"
                    class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">تخته</a>
                <a href="{{ route('orders.index', ['view' => 'list']) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-stone-600 hover:bg-stone-100">فهرست</a>
            </div>
            <x-button href="{{ route('orders.create') }}" icon="plus">سفارش جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($total === 0)
        <x-empty-state icon="clipboard" title="هنوز سفارشی ثبت نشده"
            description="یک سفارش تازه بسازید: مشتری، عنوان کار و تاریخ تحویل. همین‌قدر کافی است.">
            <x-slot:action>
                <x-button href="{{ route('orders.create') }}" icon="plus">ثبت نخستین سفارش</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div x-data="orderBoard({ statusUrl: @js(route('orders.status', ['order' => '__ID__'])) })" class="relative">
            <div x-cloak x-show="toast" x-transition
                class="fixed bottom-6 start-6 z-50 max-w-sm rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800 shadow-lg">
                <span x-text="toast"></span>
            </div>

            <div class="thin-scroll flex items-start gap-4 overflow-x-auto pb-4">
                @foreach (Order::STATUSES as $status => $meta)
                    @php($orders = $columns[$status])

                    <section class="w-72 shrink-0">
                        <header class="mb-2 flex items-center gap-2 px-1">
                            <x-badge :color="$meta['color']">{{ $meta['label'] }}</x-badge>
                            <span class="text-xs font-semibold text-stone-400"
                                data-count-for="{{ $status }}">{{ Jalali::digits($orders->count()) }}</span>
                        </header>

                        <div data-drop-zone="{{ $status }}" @dragover="onDragOver($event, '{{ $status }}')"
                            @dragleave="onDragLeave('{{ $status }}')" @drop="onDrop($event, '{{ $status }}')"
                            x-bind:class="hovered === '{{ $status }}' ? 'border-brand-400 bg-brand-50/60' : 'border-stone-200 bg-stone-50/60'"
                            class="min-h-32 space-y-2 rounded-2xl border border-dashed p-2 transition">
                            @foreach ($orders as $order)
                                <article data-order-card="{{ $order->id }}" data-status="{{ $status }}"
                                    draggable="true"
                                    @dragstart="onDragStart($event, {{ $order->id }}, '{{ $status }}')"
                                    @dragend="onDragEnd()"
                                    class="cursor-grab rounded-xl border border-stone-200 bg-white p-3 shadow-sm transition hover:shadow active:cursor-grabbing">
                                    <div class="flex items-start justify-between gap-2">
                                        <a href="{{ route('orders.show', $order) }}"
                                            class="text-sm font-bold text-stone-900 hover:text-brand-600">
                                            {{ $order->title }}
                                        </a>
                                        <span
                                            class="shrink-0 text-[11px] text-stone-400">{{ Jalali::digits($order->code) }}</span>
                                    </div>

                                    <p class="mt-1 text-xs text-stone-600">
                                        {{ $order->customer?->name ?? 'بدون مشتری' }}
                                    </p>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                        @if ($order->due_date)
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 {{ $order->isOverdue() ? 'bg-rose-100 text-rose-700' : 'bg-stone-100 text-stone-600' }}">
                                                <x-icon name="calendar" class="h-3 w-3" />
                                                {{ Jalali::date($order->due_date) }}
                                                @if ($order->isOverdue())
                                                    • دیرکرد
                                                @endif
                                            </span>
                                        @endif

                                        @if ($order->assignee)
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-sky-700">
                                                <x-icon name="user" class="h-3 w-3" />
                                                {{ $order->assignee->name }}
                                            </span>
                                        @endif

                                        @if ($order->remainingAmount() > 0)
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-amber-800">
                                                <x-icon name="money" class="h-3 w-3" />
                                                مانده {{ Format::money($order->remainingAmount()) }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- راه بدون موس: همین درخواست را با فرم معمولی می‌فرستد --}}
                                    <form method="POST" action="{{ route('orders.status', $order) }}"
                                        class="mt-2.5 flex items-center gap-1 border-t border-stone-100 pt-2">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="status-{{ $order->id }}">
                                            وضعیت سفارش {{ $order->code }}
                                        </label>
                                        <select id="status-{{ $order->id }}" name="status" data-status-select
                                            class="w-full rounded-lg border-stone-200 bg-stone-50 px-2 py-1 text-[11px] focus:border-brand-500 focus:ring-brand-400">
                                            @foreach ($statusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($value === $status)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="shrink-0 rounded-lg bg-stone-100 p-1.5 text-stone-600 transition hover:bg-brand-600 hover:text-white"
                                            title="ثبت وضعیت">
                                            <x-icon name="check" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </article>
                            @endforeach

                            <p data-empty-column @class(['py-6 text-center text-xs text-stone-400'])
                                @style(['display: none' => $orders->isNotEmpty()])>
                                کارتی در این ستون نیست
                            </p>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
