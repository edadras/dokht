<x-app-layout :title="$listing->title">
    <x-page-header :title="$listing->title"
        :subtitle="'فروشنده: '.($listing->sellerWorkshop?->name ?? 'کارگاه ناشناس')"
        :back="route('market.index')">
        <x-slot:actions>
            @if ($isMine)
                <x-button href="{{ route('market.sales') }}" variant="secondary" icon="money">فروش‌های من</x-button>
                <x-confirm-delete :action="route('market.listings.destroy', $listing)" size="md"
                    label="برداشتن آگهی"
                    message="آگهی از ویترین برداشته می‌شود. سفارش‌های ثبت‌شده و نسخه‌های تحویل‌شده دست‌نخورده می‌مانند. ادامه می‌دهید؟" />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $listing->is_active)
        <x-alert type="warning" class="mb-6">
            این آگهی غیرفعال است و روی ویترین بازارچه دیده نمی‌شود؛ فقط شما آن را می‌بینید.
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="پیش‌نمایش" icon="eye"
                subtitle="سایه قطعه‌ها: شمار و نسبت اندازه‌ها را نشان می‌دهد، نه خط‌های الگو را.">
                <div class="flex min-h-56 items-center justify-center rounded-xl bg-stone-50 p-4">
                    @if ($listing->previewValue('silhouette'))
                        <div class="w-full [&>svg]:h-auto [&>svg]:w-full">{!! $listing->previewValue('silhouette') !!}</div>
                    @else
                        <div class="py-10 text-center">
                            <x-icon name="scissors" class="mx-auto h-10 w-10 text-stone-300" />
                            <p class="mt-2 text-sm text-stone-500">پیش‌نمایشی برای این آگهی ثبت نشده است.</p>
                        </div>
                    @endif
                </div>

                @if ($listing->description)
                    <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-stone-700">{{ $listing->description }}</p>
                @endif
            </x-card>

            <x-card title="چه چیزی تحویل می‌گیرید؟" icon="box">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">شمار قطعه</dt>
                        <dd class="font-medium">
                            {{ \App\Support\Jalali::digits($listing->previewValue('piece_count', 0)) }} قطعه
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">مجموع برش</dt>
                        <dd class="font-medium">
                            {{ \App\Support\Jalali::digits($listing->previewValue('cut_count', 0)) }} تکه
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">سایز پایه</dt>
                        <dd class="font-medium">
                            {{ \App\Support\Jalali::digits($listing->previewValue('base_size', '—')) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">بازه سایزبندی</dt>
                        <dd class="font-medium">{{ $listing->previewValue('size_range', '—') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">نوع لباس</dt>
                        <dd class="font-medium">{{ $listing->garmentType?->name_fa ?? '—' }}</dd>
                    </div>
                    @if ($largest = $listing->previewValue('largest_piece'))
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-stone-500">بزرگ‌ترین قطعه</dt>
                            <dd class="font-medium">
                                {{ \App\Support\Format::cm($largest['width'] ?? null) }}
                                ×
                                {{ \App\Support\Format::cm($largest['height'] ?? null) }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($layers = $listing->previewValue('layers', []))
                    <div class="mt-4 flex flex-wrap gap-1.5 border-t border-stone-100 pt-4">
                        @foreach ($layers as $label => $count)
                            <x-badge color="slate" icon="layers">
                                {{ $label }}: {{ \App\Support\Jalali::digits($count) }}
                            </x-badge>
                        @endforeach
                    </div>
                @endif

                @if ($fabrics = $listing->previewValue('fabrics', []))
                    <div class="mt-4 border-t border-stone-100 pt-4">
                        <p class="mb-2 text-sm font-semibold text-stone-700">پارچه پیشنهادی</p>
                        <ul class="space-y-1 text-sm text-stone-600">
                            @foreach ($fabrics as $suggestion)
                                <li class="flex items-start gap-2">
                                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>{{ $suggestion }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="mt-4 border-t border-stone-100 pt-4 text-xs leading-relaxed text-stone-500">
                    پس از تأیید فروشنده، یک نسخه کامل و مستقل از الگو (با همه قطعه‌ها و جای دوخت) در کارگاه شما ساخته
                    می‌شود و می‌توانید آزادانه تغییرش دهید.
                </p>
            </x-card>

            @if ($isMine)
                <x-card title="ویرایش آگهی" icon="edit">
                    <form method="POST" action="{{ route('market.listings.update', $listing) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-field label="عنوان" name="title" required>
                            <x-input name="title" :value="$listing->title" />
                        </x-field>

                        <x-field label="قیمت" name="price" required hint="مبلغ به تومان؛ صفر یعنی رایگان.">
                            <x-input name="price" :value="(int) $listing->price" inputmode="numeric" />
                        </x-field>

                        <x-field label="توضیح" name="description">
                            <x-textarea name="description" :value="$listing->description" rows="4" />
                        </x-field>

                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($listing->is_active)
                                class="h-4 w-4 rounded border-stone-300 text-brand-600">
                            روی ویترین بازارچه نمایش داده شود
                        </label>

                        <x-button type="submit" icon="check">ذخیره آگهی</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="قیمت و سفارش" icon="money">
                <p class="text-2xl font-black text-stone-900">{{ $listing->priceLabel() }}</p>

                <x-alert type="warning" class="mt-4">
                    پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید می‌کند.
                </x-alert>

                @if ($isMine)
                    <p class="mt-4 text-sm text-stone-500">این آگهی خودِ کارگاه شماست.</p>
                @elseif ($existing && $existing->isDelivered())
                    <x-alert type="success" class="mt-4">
                        نسخه این الگو پیش‌تر به کارگاه شما تحویل شده است.
                    </x-alert>
                    @if ($existingCopy)
                        <x-button href="{{ route('patterns.show', $existingCopy) }}" icon="eye" class="mt-4 w-full">
                            دیدن نسخه خودم
                        </x-button>
                    @endif
                @elseif ($existing)
                    <x-alert type="info" class="mt-4">
                        برای این الگو سفارش بازی دارید: {{ $existing->statusLabel() }}.
                    </x-alert>
                    <x-button href="{{ route('market.purchases') }}" variant="secondary" icon="box" class="mt-4 w-full">
                        دنبال‌کردن سفارش
                    </x-button>
                @else
                    <form method="POST" action="{{ route('market.order', $listing) }}" class="mt-4 space-y-3">
                        @csrf

                        <x-field label="یادداشت برای فروشنده" name="buyer_note">
                            <x-textarea name="buyer_note" rows="2" placeholder="اختیاری؛ مثلاً روش هماهنگی پرداخت" />
                        </x-field>

                        <x-button type="submit" size="lg" icon="money" class="w-full">سفارش این الگو</x-button>
                    </form>
                @endif
            </x-card>

            <x-card title="فروشنده" icon="user">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">کارگاه</dt>
                        <dd class="font-medium">{{ $listing->sellerWorkshop?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">شهر</dt>
                        <dd class="font-medium">{{ $listing->sellerWorkshop?->city ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">فروش این آگهی</dt>
                        <dd class="font-medium">{{ \App\Support\Jalali::digits($listing->sales_count) }} بار</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">تاریخ آگهی</dt>
                        <dd class="font-medium">{{ \App\Support\Jalali::date($listing->created_at) }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="این خرید چه چیزی را تضمین نمی‌کند؟" icon="info">
                <ul class="space-y-2 text-sm leading-relaxed text-stone-600">
                    <li>«دوخت» درگاه پرداخت ندارد؛ هیچ مبلغی از این صفحه جابه‌جا نمی‌شود.</li>
                    <li>هماهنگی و پرداخت وجه میان دو کارگاه، بیرون از سامانه انجام می‌شود.</li>
                    <li>ثبت «پرداخت تأییدشده» یعنی فروشنده گفته است وجه را گرفته‌ام؛ سامانه آن را نمی‌سنجد.</li>
                    <li>پس از تحویل، نسخه شما مستقل است و تغییرهای بعدی فروشنده به آن نمی‌رسد.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
