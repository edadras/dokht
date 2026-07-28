<x-app-layout title="خریدهای من" wide>
    <x-page-header title="خریدهای من" subtitle="سفارش‌هایی که از بازارچه ثبت کرده‌اید."
        :back="route('market.index')">
        <x-slot:actions>
            <x-button href="{{ route('market.sales') }}" variant="secondary" icon="money">فروش‌های من</x-button>
            <x-button href="{{ route('market.index') }}" icon="search">گشتن در بازارچه</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="info" class="mb-6">
        پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید می‌کند.
        آن‌گاه دکمه «برداشتن نسخه» فعال می‌شود و یک نسخه مستقل از الگو در کارگاه شما ساخته می‌شود.
    </x-alert>

    @if ($purchases->isEmpty())
        <x-empty-state icon="box" title="هنوز خریدی ثبت نکرده‌اید"
            description="در بازارچه بگردید و الگوی آماده کارگاه‌های دیگر را سفارش دهید.">
            <x-slot:action>
                <x-button href="{{ route('market.index') }}" icon="search">رفتن به بازارچه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">آگهی</th>
                            <th class="px-4 py-3 text-start font-medium">فروشنده</th>
                            <th class="px-4 py-3 text-start font-medium">مبلغ</th>
                            <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-start font-medium">تاریخ سفارش</th>
                            <th class="px-4 py-3 text-start font-medium">کار بعدی</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">
                                        {{ $purchase->listing?->title ?? 'آگهی حذف‌شده' }}
                                    </span>
                                    @if ($purchase->listing && $purchase->listing->deleted_at === null)
                                        <a href="{{ route('market.show', $purchase->listing) }}"
                                            class="ms-2 text-xs text-brand-600 hover:underline">دیدن آگهی</a>
                                    @endif
                                    @if ($purchase->buyer_note)
                                        <p class="mt-1 text-xs text-stone-500">یادداشت شما: {{ $purchase->buyer_note }}</p>
                                    @endif
                                    @if ($purchase->seller_note)
                                        <p class="mt-1 text-xs text-stone-500">یادداشت فروشنده: {{ $purchase->seller_note }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ $purchase->sellerWorkshop?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-medium text-stone-800">{{ $purchase->priceLabel() }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$purchase->statusColor()">{{ $purchase->statusLabel() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::date($purchase->ordered_at ?? $purchase->created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($purchase->isPaid())
                                        <form method="POST" action="{{ route('market.purchases.copy', $purchase) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" icon="copy">برداشتن نسخه</x-button>
                                        </form>
                                    @elseif ($purchase->isDelivered() && $purchase->deliveredPattern)
                                        <x-button href="{{ route('patterns.show', $purchase->deliveredPattern) }}"
                                            size="sm" variant="secondary" icon="eye">دیدن الگوی من</x-button>
                                    @elseif ($purchase->isPending())
                                        <span class="text-xs text-stone-500">در انتظار تأیید پرداخت از سوی فروشنده</span>
                                    @else
                                        <span class="text-xs text-stone-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>
