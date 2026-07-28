<x-app-layout title="فروش‌های من" wide>
    <x-page-header title="فروش‌های من" subtitle="آگهی‌های کارگاه شما و سفارش‌هایی که دریافت کرده‌اید."
        :back="route('market.index')">
        <x-slot:actions>
            <x-button href="{{ route('market.purchases') }}" variant="secondary" icon="box">خریدهای من</x-button>
            <x-button type="button" icon="plus" x-on:click="$dispatch('open-modal-new-listing')">
                گذاشتن آگهی تازه
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="info" class="mb-6">
        پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید می‌کند.
        تا وقتی شما دریافت وجه را تأیید نکنید، خریدار نمی‌تواند نسخه الگو را بردارد.
    </x-alert>

    {{-- فرم آگهی تازه: سه فیلد، بقیه چیزها خودکار --}}
    <x-modal name="new-listing" title="گذاشتن آگهی تازه" max-width="max-w-xl">
        @if ($sellablePatterns->isEmpty())
            <p class="text-sm text-stone-600">
                همه الگوهای کارگاه شما آگهی فعال دارند، یا هنوز الگویی نساخته‌اید.
            </p>
        @else
            <form method="POST" action="{{ route('market.listings.store') }}" class="space-y-4">
                @csrf

                <x-field label="الگو" name="pattern_id" required hint="فقط الگوهای کارگاه خودتان.">
                    <x-select name="pattern_id" placeholder="انتخاب الگو"
                        :options="$sellablePatterns->pluck('name', 'id')->all()" />
                </x-field>

                <x-field label="عنوان" name="title" required>
                    <x-input name="title" placeholder="مثلاً: مانتو جلوباز کلاسیک" />
                </x-field>

                <x-field label="قیمت" name="price" required hint="مبلغ به تومان؛ صفر یعنی رایگان.">
                    <x-input name="price" inputmode="numeric" placeholder="۲۵۰۰۰۰" />
                </x-field>

                <x-field label="توضیح" name="description">
                    <x-textarea name="description" rows="4"
                        placeholder="برای چه اندامی مناسب است، چه پارچه‌ای بخورد، چه نکته دوختی دارد…" />
                </x-field>

                <x-button type="submit" icon="check" class="w-full">گذاشتن آگهی</x-button>
            </form>
        @endif
    </x-modal>

    <x-card title="آگهی‌های من" icon="share" padding="p-0" class="mb-6">
        @if ($listings->isEmpty())
            <div class="p-5">
                <x-empty-state icon="share" title="هنوز آگهی‌ای نگذاشته‌اید"
                    description="الگویی از کارگاه خودتان را انتخاب کنید، عنوان و قیمت بدهید و روی ویترین بگذارید." />
            </div>
        @else
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">عنوان</th>
                            <th class="px-4 py-3 text-start font-medium">الگو</th>
                            <th class="px-4 py-3 text-start font-medium">قیمت</th>
                            <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-start font-medium">سفارش‌ها</th>
                            <th class="px-4 py-3 text-start font-medium">تاریخ</th>
                            <th class="px-4 py-3 text-end font-medium">کنش</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($listings as $listing)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('market.show', $listing) }}"
                                        class="font-medium text-stone-800 hover:text-brand-600">{{ $listing->title }}</a>
                                </td>
                                <td class="px-4 py-3 text-stone-600">{{ $listing->pattern?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-stone-800">{{ $listing->priceLabel() }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$listing->is_active ? 'emerald' : 'slate'">
                                        {{ $listing->is_active ? 'روی ویترین' : 'غیرفعال' }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($listing->purchases_count) }}
                                    <span class="text-xs text-stone-400">
                                        ({{ \App\Support\Jalali::digits($listing->sales_count) }} تحویل‌شده)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-stone-600">{{ \App\Support\Jalali::date($listing->created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button href="{{ route('market.show', $listing) }}" size="sm"
                                            variant="ghost" icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('market.listings.destroy', $listing)"
                                            label="برداشتن"
                                            message="آگهی از ویترین برداشته می‌شود. سفارش‌های ثبت‌شده و نسخه‌های تحویل‌شده دست‌نخورده می‌مانند. ادامه می‌دهید؟" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="سفارش‌های دریافتی" icon="money" padding="p-0">
        @if ($orders->isEmpty())
            <div class="p-5">
                <x-empty-state icon="money" title="هنوز سفارشی نرسیده است"
                    description="وقتی کارگاهی الگوی شما را سفارش دهد، اینجا دیده می‌شود." />
            </div>
        @else
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">آگهی</th>
                            <th class="px-4 py-3 text-start font-medium">خریدار</th>
                            <th class="px-4 py-3 text-start font-medium">مبلغ</th>
                            <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-start font-medium">تاریخ سفارش</th>
                            <th class="px-4 py-3 text-end font-medium">کنش</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($orders as $order)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">
                                        {{ $order->listing?->title ?? 'آگهی حذف‌شده' }}
                                    </span>
                                    @if ($order->buyer_note)
                                        <p class="mt-1 text-xs text-stone-500">یادداشت خریدار: {{ $order->buyer_note }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ $order->buyerWorkshop?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-medium text-stone-800">{{ $order->priceLabel() }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$order->statusColor()">{{ $order->statusLabel() }}</x-badge>
                                    @if ($order->paid_at)
                                        <p class="mt-1 text-xs text-stone-500">
                                            تأیید دریافت وجه: {{ \App\Support\Jalali::date($order->paid_at) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::date($order->ordered_at ?? $order->created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($order->isPending())
                                        <div class="flex items-center justify-end gap-1">
                                            <form method="POST" action="{{ route('market.purchases.confirm', $order) }}"
                                                onsubmit="return confirm('تأیید می‌کنید که وجه این سفارش را بیرون از سامانه دریافت کرده‌اید؟')">
                                                @csrf
                                                <x-button type="submit" size="sm" icon="check">دریافت وجه را تأیید می‌کنم</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('market.purchases.cancel', $order) }}"
                                                onsubmit="return confirm('این سفارش لغو شود؟')">
                                                @csrf
                                                <x-button type="submit" size="sm" variant="ghost" icon="x"
                                                    class="text-rose-600 hover:bg-rose-50">لغو</x-button>
                                            </form>
                                        </div>
                                    @elseif ($order->isPaid())
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="text-xs text-stone-500">در انتظار برداشتن نسخه توسط خریدار</span>
                                            <form method="POST" action="{{ route('market.purchases.cancel', $order) }}"
                                                onsubmit="return confirm('این سفارش لغو شود؟')">
                                                @csrf
                                                <x-button type="submit" size="sm" variant="ghost" icon="x"
                                                    class="text-rose-600 hover:bg-rose-50">لغو</x-button>
                                            </form>
                                        </div>
                                    @else
                                        <p class="text-end text-xs text-stone-500">
                                            @if ($order->isDelivered())
                                                تحویل شد: {{ \App\Support\Jalali::date($order->delivered_at) }}
                                            @else
                                                لغو شد: {{ \App\Support\Jalali::date($order->cancelled_at) }}
                                            @endif
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
