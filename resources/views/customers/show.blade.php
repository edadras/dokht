@use('App\Support\Format')
@use('App\Support\Jalali')

<x-app-layout :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->genderLabel().($customer->phone ? ' • '.Jalali::digits($customer->phone) : '')"
        :back="route('customers.index')">
        <x-slot:actions>
            <x-button href="{{ route('orders.create', ['customer' => $customer->id]) }}" icon="plus">سفارش جدید</x-button>
            <x-button href="{{ route('measurements.create', $customer) }}" variant="secondary" icon="ruler">
                اندازه جدید
            </x-button>
            <x-button href="{{ route('customers.edit', $customer) }}" variant="ghost" icon="edit">ویرایش</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- دفترچه اندازه --}}
            <x-card title="دفترچه اندازه" icon="ruler"
                subtitle="هر بار که اندازه می‌گیرید یک برگ تازه ثبت کنید تا تاریخچه بماند.">
                <x-slot:actions>
                    <x-button href="{{ route('measurements.create', $customer) }}" size="sm" icon="plus">
                        اندازه جدید
                    </x-button>
                </x-slot:actions>

                @if ($customer->measurementSets->isEmpty())
                    <x-empty-state icon="ruler" title="هنوز اندازه‌ای ثبت نشده"
                        description="فقط شش عدد لازم است: قد، دور سینه، دور کمر، دور باسن، عرض سرشانه و قد آستین. باقی اندازه‌ها خودکار تخمین زده می‌شود.">
                        <x-slot:action>
                            <x-button href="{{ route('measurements.create', $customer) }}" icon="ruler">
                                گرفتن اندازه
                            </x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <div class="space-y-3">
                        @foreach ($customer->measurementSets as $set)
                            <div class="rounded-xl border border-stone-200 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-stone-800">{{ $set->displayTitle() }}</p>

                                    @if ($set->is_default)
                                        <x-badge color="emerald" icon="check">اندازه اصلی</x-badge>
                                    @endif

                                    @if ($set->base_size)
                                        <x-badge color="brand">سایز {{ Jalali::digits($set->base_size) }}</x-badge>
                                    @endif

                                    <span class="ms-auto text-xs text-stone-400">
                                        {{ Jalali::date($set->created_at, true) }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-stone-600">{{ $set->summary() ?: 'بدون عدد' }}</p>

                                @if ($set->notes)
                                    <p class="mt-1 text-xs text-stone-500">{{ $set->notes }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <x-button href="{{ route('measurements.edit', $set) }}" size="sm" variant="secondary"
                                        icon="edit">ویرایش</x-button>
                                    <x-button
                                        href="{{ route('measurements.create', ['customer' => $customer, 'copy' => $set->id]) }}"
                                        size="sm" variant="ghost" icon="copy">کپی به برگ تازه</x-button>
                                    <x-confirm-delete :action="route('measurements.destroy', $set)"
                                        message="این مجموعه اندازه حذف شود؟" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- سفارش‌ها --}}
            <x-card title="سفارش‌ها" icon="clipboard">
                <x-slot:actions>
                    <x-button href="{{ route('orders.create', ['customer' => $customer->id]) }}" size="sm" icon="plus">
                        سفارش جدید
                    </x-button>
                </x-slot:actions>

                @if ($orders->isEmpty())
                    <x-empty-state icon="clipboard" title="سفارشی برای این مشتری ثبت نشده"
                        description="یک سفارش تازه با عنوان و تاریخ تحویل بسازید." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-stone-500">
                                <tr class="border-b border-stone-200">
                                    <th class="px-2 py-2 text-start">کد</th>
                                    <th class="px-2 py-2 text-start">عنوان</th>
                                    <th class="px-2 py-2 text-start">وضعیت</th>
                                    <th class="px-2 py-2 text-start">تحویل</th>
                                    <th class="px-2 py-2 text-start">مانده</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($orders as $order)
                                    <tr>
                                        <td class="px-2 py-2.5 whitespace-nowrap text-stone-500">
                                            {{ Jalali::digits($order->code) }}
                                        </td>
                                        <td class="px-2 py-2.5">
                                            <a href="{{ route('orders.show', $order) }}"
                                                class="font-medium hover:text-brand-600">{{ $order->title }}</a>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            <x-badge :color="$order->statusColor()">{{ $order->statusLabel() }}</x-badge>
                                        </td>
                                        <td class="px-2 py-2.5 whitespace-nowrap">
                                            {{ Jalali::date($order->due_date) }}
                                            @if ($order->isOverdue())
                                                <span class="text-xs font-semibold text-rose-600">دیرکرد</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2.5 whitespace-nowrap">
                                            {{ Format::money($order->remainingAmount()) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- پروژه‌ها --}}
            <x-card title="پروژه‌های دوخت" icon="shirt">
                @if ($customer->projects->isEmpty())
                    <p class="text-sm text-stone-500">پروژه‌ای برای این مشتری ساخته نشده است.</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($customer->projects as $project)
                            <a href="{{ route('projects.show', $project) }}"
                                class="rounded-xl border border-stone-200 p-3 transition hover:border-brand-300">
                                <p class="font-semibold text-stone-800">{{ $project->name }}</p>
                                <p class="mt-0.5 text-xs text-stone-500">
                                    {{ $project->statusLabel() }} • گام {{ Jalali::digits($project->step) }}
                                </p>
                                <x-meter class="mt-2" :value="$project->progressPercent()" label="پیشرفت" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="اطلاعات تماس" icon="user">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">شماره تماس</dt>
                        <dd class="font-medium" dir="ltr">
                            {{ $customer->phone ? Jalali::digits($customer->phone) : '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">ایمیل</dt>
                        <dd class="truncate font-medium" dir="ltr">{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">تاریخ تولد</dt>
                        <dd class="font-medium">{{ Jalali::date($customer->birth_date) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">در دفتر از</dt>
                        <dd class="font-medium">{{ Jalali::date($customer->created_at) }}</dd>
                    </div>
                </dl>

                @if ($customer->notes)
                    <p class="mt-4 rounded-xl bg-stone-50 p-3 text-sm text-stone-600">{{ $customer->notes }}</p>
                @endif
            </x-card>

            <x-card title="حساب و کتاب" icon="money">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">کل سفارش‌ها</dt>
                        <dd class="font-bold">{{ Format::money($totals['ordered']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">پرداخت‌شده</dt>
                        <dd class="font-bold text-emerald-700">{{ Format::money($totals['paid']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-stone-500">مانده</dt>
                        <dd class="font-bold {{ $totals['remaining'] > 0 ? 'text-rose-600' : 'text-stone-700' }}">
                            {{ Format::money($totals['remaining']) }}
                        </dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
