@use('App\Models\Order')
@use('App\Models\OrderItem')
@use('App\Models\Payment')
@use('App\Support\Format')
@use('App\Support\Jalali')

@php
    $flow = collect(Order::STATUSES)->except('cancelled');
    $currentIndex = $flow->keys()->search($order->status);
    $measurementSet = $order->customer?->defaultMeasurementSet;
@endphp

<x-app-layout :title="'سفارش '.$order->code">
    <x-page-header :title="$order->title" :subtitle="'کد '.Jalali::digits($order->code).' • '.($order->customer?->name ?? 'بدون مشتری')"
        :back="route('orders.index')">
        <x-slot:actions>
            <x-button href="{{ route('orders.invoice', $order) }}" variant="secondary" icon="print" target="_blank">
                فاکتور
            </x-button>
            <x-button href="{{ route('orders.edit', $order) }}" variant="ghost" icon="edit">ویرایش</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- گام‌های کار --}}
    <x-card class="mb-6" padding="p-4">
        <form method="POST" action="{{ route('orders.status', $order) }}">
            @csrf
            @method('PATCH')

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($flow as $status => $meta)
                    @php($isDone = $currentIndex !== false && $flow->keys()->search($status) <= $currentIndex)

                    <button type="submit" name="status" value="{{ $status }}"
                        class="flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition
                            {{ $order->status === $status
                                ? 'border-brand-500 bg-brand-600 text-white'
                                : ($isDone
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                    : 'border-stone-200 bg-white text-stone-600 hover:border-brand-300 hover:text-brand-700') }}">
                        @if ($isDone && $order->status !== $status)
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        @endif
                        {{ $meta['label'] }}
                    </button>

                    @if (! $loop->last)
                        <x-icon name="chevron-left" class="h-4 w-4 text-stone-300" />
                    @endif
                @endforeach

                <button type="submit" name="status" value="cancelled"
                    class="ms-auto rounded-xl border px-3 py-2 text-xs font-semibold transition
                        {{ $order->status === 'cancelled' ? 'border-rose-400 bg-rose-600 text-white' : 'border-stone-200 text-rose-600 hover:bg-rose-50' }}">
                    لغو سفارش
                </button>
            </div>
        </form>

        @if ($order->delivered_at)
            <p class="mt-3 text-xs text-emerald-700">
                تحویل‌شده در {{ Jalali::dateTime($order->delivered_at) }}
            </p>
        @elseif ($order->isOverdue())
            <p class="mt-3 text-xs font-semibold text-rose-600">
                تاریخ تحویل ({{ Jalali::date($order->due_date, true) }}) گذشته است.
            </p>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- بیل مواد --}}
            <x-card title="بیل مواد و خدمات" icon="layers" subtitle="پارچه، متعلقات و کار انجام‌شده روی این سفارش.">
                @if ($order->items->isEmpty())
                    <p class="text-sm text-stone-500">هنوز ردیفی ثبت نشده است.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-stone-500">
                                <tr class="border-b border-stone-200">
                                    <th class="px-2 py-2 text-start">شرح</th>
                                    <th class="px-2 py-2 text-start">نوع</th>
                                    <th class="px-2 py-2 text-start">مقدار</th>
                                    <th class="px-2 py-2 text-start">قیمت واحد</th>
                                    <th class="px-2 py-2 text-start">جمع</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td class="px-2 py-2.5 font-medium">{{ $item->description }}</td>
                                        <td class="px-2 py-2.5 text-xs text-stone-500">{{ $item->kindLabel() }}</td>
                                        <td class="px-2 py-2.5 whitespace-nowrap">
                                            {{ Format::number($item->quantity, 2) }} {{ $item->unit }}
                                        </td>
                                        <td class="px-2 py-2.5 whitespace-nowrap">
                                            {{ Format::money($item->unit_price) }}
                                        </td>
                                        <td class="px-2 py-2.5 whitespace-nowrap font-semibold">
                                            {{ Format::money($item->total()) }}
                                        </td>
                                        <td class="px-2 py-2.5 text-end">
                                            <x-confirm-delete :action="route('order-items.destroy', $item)"
                                                message="این ردیف حذف شود؟" label="" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-stone-200">
                                    <td colspan="4" class="px-2 py-2.5 text-end text-xs text-stone-500">جمع ردیف‌ها</td>
                                    <td class="px-2 py-2.5 font-bold">{{ Format::money($order->itemsTotal()) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                {{-- افزودن ردیف --}}
                <form method="POST" action="{{ route('order-items.store', $order) }}"
                    x-data="{
                        kind: 'fabric',
                        fabric: '',
                        material: '',
                        unitPrice: '',
                        fabricPrices: @js($fabrics->pluck('price_per_meter', 'id')),
                        materialPrices: @js($materials->pluck('price', 'id')),
                        pick() {
                            const price = this.kind === 'fabric' ? this.fabricPrices[this.fabric] : this.materialPrices[this.material];
                            if (price) this.unitPrice = String(Math.round(price));
                        },
                    }"
                    class="mt-5 space-y-4 rounded-2xl border border-dashed border-stone-300 bg-stone-50 p-4">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <x-field label="نوع ردیف" name="kind">
                            <select name="kind" x-model="kind"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                @foreach (OrderItem::KINDS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <div x-show="kind === 'fabric'">
                            <x-field label="پارچه" name="fabric_id">
                                <select name="fabric_id" x-model="fabric" @change="pick()"
                                    class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                    <option value="">— انتخاب پارچه —</option>
                                    @foreach ($fabrics as $fabric)
                                        <option value="{{ $fabric->id }}">{{ $fabric->displayName() }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                        </div>

                        <div x-cloak x-show="kind === 'material'">
                            <x-field label="متعلقات" name="material_id">
                                <select name="material_id" x-model="material" @change="pick()"
                                    class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                    <option value="">— انتخاب —</option>
                                    @foreach ($materials as $material)
                                        <option value="{{ $material->id }}">{{ $material->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                        </div>

                        <x-field label="شرح" name="description"
                            :hint="'برای پارچه و متعلقات خالی بگذارید تا نامش نوشته شود.'">
                            <x-input name="description" placeholder="مثلاً دوخت آستری" />
                        </x-field>

                        <x-field label="مقدار" name="quantity">
                            <x-input name="quantity" value="1" inputmode="decimal" />
                        </x-field>

                        <x-field label="یکا" name="unit">
                            <x-input name="unit" x-bind:placeholder="kind === 'fabric' ? 'متر' : 'عدد'"
                                placeholder="عدد" />
                        </x-field>

                        <x-field label="قیمت واحد" name="unit_price" suffix="تومان">
                            <x-input name="unit_price" x-model="unitPrice" inputmode="decimal" class="pe-16"
                                placeholder="۰" />
                        </x-field>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-stone-500">
                            برای پارچه، یکا «متر» و قیمت از پارچه کارگاه خودکار پر می‌شود.
                        </p>
                        <x-button type="submit" size="sm" icon="plus">افزودن ردیف</x-button>
                    </div>
                </form>
            </x-card>

            {{-- پرداخت‌ها --}}
            <x-card title="پرداخت‌ها" icon="money">
                @if ($order->payments->isEmpty())
                    <p class="text-sm text-stone-500">پرداختی ثبت نشده است.</p>
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($order->payments as $payment)
                            <li class="flex flex-wrap items-center gap-3 py-2.5">
                                <span class="font-bold">{{ Format::money($payment->amount) }}</span>
                                <x-badge color="slate">{{ $payment->methodLabel() }}</x-badge>
                                <span class="text-xs text-stone-500">{{ Jalali::date($payment->paid_at) }}</span>
                                @if ($payment->note)
                                    <span class="text-xs text-stone-500">{{ $payment->note }}</span>
                                @endif
                                <span class="ms-auto">
                                    <x-confirm-delete :action="route('payments.destroy', $payment)"
                                        message="این پرداخت حذف شود؟" label="" />
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('payments.store', $order) }}"
                    class="mt-4 grid gap-3 rounded-2xl border border-dashed border-stone-300 bg-stone-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf

                    <x-field label="مبلغ" name="amount" suffix="تومان">
                        <x-input name="amount" inputmode="decimal" class="pe-16"
                            :value="$order->remainingAmount() > 0 ? (int) $order->remainingAmount() : null" />
                    </x-field>

                    <x-field label="روش" name="method">
                        <x-select name="method" :options="Payment::METHODS" selected="cash" />
                    </x-field>

                    <x-field label="تاریخ پرداخت" name="paid_at" hint="خالی = امروز">
                        <x-input name="paid_at" :placeholder="Jalali::date(now())" />
                    </x-field>

                    <div class="flex items-end">
                        <x-button type="submit" icon="plus">ثبت پرداخت</x-button>
                    </div>

                    <x-field label="توضیح" name="note" class="sm:col-span-2 lg:col-span-4">
                        <x-input name="note" placeholder="مثلاً بیعانه دوم" />
                    </x-field>
                </form>
            </x-card>

            @if ($order->notes)
                <x-card title="یادداشت سفارش" icon="file">
                    <p class="text-sm whitespace-pre-line text-stone-700">{{ $order->notes }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="حساب سفارش" icon="money">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">مبلغ کل</dt>
                        <dd class="font-bold">{{ Format::money($order->price) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">بیعانه</dt>
                        <dd>{{ Format::money($order->deposit) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">پرداخت‌شده</dt>
                        <dd class="font-bold text-emerald-700">{{ Format::money($order->paidAmount()) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-stone-100 pt-3">
                        <dt class="text-stone-500">مانده</dt>
                        <dd class="font-black {{ $order->remainingAmount() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                            {{ Format::money($order->remainingAmount()) }}
                        </dd>
                    </div>
                </dl>

                @if ($order->price > 0)
                    <x-meter class="mt-4" label="درصد پرداخت" :value="$order->paidAmount()" :max="$order->price" />
                @endif
            </x-card>

            <x-card title="مشتری و اندازه" icon="user">
                @if ($order->customer)
                    <a href="{{ route('customers.show', $order->customer) }}"
                        class="font-bold text-stone-900 hover:text-brand-600">{{ $order->customer->name }}</a>
                    <p class="mt-0.5 text-xs text-stone-500" dir="ltr">
                        {{ $order->customer->phone ? Jalali::digits($order->customer->phone) : '' }}
                    </p>

                    @if ($measurementSet)
                        <div class="mt-3 rounded-xl bg-stone-50 p-3">
                            <p class="text-xs text-stone-500">{{ $measurementSet->displayTitle() }}</p>
                            <p class="mt-1 text-sm font-medium">{{ $measurementSet->summary() ?: 'بدون عدد' }}</p>
                            @if ($measurementSet->base_size)
                                <p class="mt-1 text-xs text-stone-500">
                                    سایز {{ Jalali::digits($measurementSet->base_size) }}
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="mt-3 text-xs text-amber-700">اندازه‌ای برای این مشتری ثبت نشده است.</p>
                        <x-button class="mt-2" href="{{ route('measurements.create', $order->customer) }}" size="sm"
                            variant="secondary" icon="ruler">گرفتن اندازه</x-button>
                    @endif
                @else
                    <p class="text-sm text-stone-500">مشتری‌ای به این سفارش وصل نیست.</p>
                @endif
            </x-card>

            <x-card title="پروژه و الگو" icon="shirt">
                @if ($order->project)
                    <a href="{{ route('projects.show', $order->project) }}"
                        class="font-semibold hover:text-brand-600">{{ $order->project->name }}</a>
                    <x-meter class="mt-2" label="پیشرفت پروژه" :value="$order->project->progressPercent()" />

                    @if ($order->project->pattern)
                        <a href="{{ route('patterns.show', $order->project->pattern) }}"
                            class="mt-3 flex items-center gap-2 rounded-xl bg-stone-50 p-3 text-sm hover:bg-stone-100">
                            <x-icon name="scissors" class="h-4 w-4 text-stone-400" />
                            {{ $order->project->pattern->name }}
                        </a>
                    @endif
                @else
                    <p class="text-sm text-stone-500">پروژه‌ای وصل نشده است.</p>
                @endif
            </x-card>

            <x-card title="مشخصات کار" icon="info">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">تاریخ تحویل</dt>
                        <dd class="font-medium">{{ Jalali::date($order->due_date, true) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">مسئول کار</dt>
                        <dd class="font-medium">{{ $order->assignee?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">ثبت شده</dt>
                        <dd class="font-medium">{{ Jalali::date($order->created_at) }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
