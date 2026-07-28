<x-app-layout title="بازارچه الگو" wide>
    <x-page-header title="بازارچه الگو"
        subtitle="الگوی آماده کارگاه‌های دیگر را بخرید، یا الگوی خودتان را بفروشید.">
        <x-slot:actions>
            <x-button href="{{ route('market.purchases') }}" variant="secondary" icon="box">خریدهای من</x-button>
            <x-button href="{{ route('market.sales') }}" icon="money">فروش‌های من</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="info" class="mb-6">
        پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید می‌کند.
        هیچ پولی در «دوخت» جابه‌جا نمی‌شود.
    </x-alert>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="آگهی‌های فعال من" :value="\App\Support\Format::number($myListings)" icon="share" color="brand"
            :href="route('market.sales')" />
        <x-stat label="خریدهای در جریان" :value="\App\Support\Format::number($myPurchases)" icon="box" color="sky"
            :href="route('market.purchases')" />
        <x-stat label="فروش‌های در جریان" :value="\App\Support\Format::number($mySales)" icon="money" color="emerald"
            :href="route('market.sales')" />
    </div>

    <form method="GET" action="{{ route('market.index') }}"
        class="mb-6 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="عنوان آگهی…" />
        </x-field>

        <x-field label="نوع لباس" name="garment_type">
            <x-select name="garment_type" placeholder="همه" :selected="$garmentTypeId"
                :options="$garmentTypes->pluck('name_fa', 'id')->all()" />
        </x-field>

        <x-field label="کمترین قیمت" name="min_price">
            <x-input name="min_price" :value="$minPrice" inputmode="numeric" placeholder="۰" />
        </x-field>

        <x-field label="بیشترین قیمت" name="max_price">
            <x-input name="max_price" :value="$maxPrice" inputmode="numeric" placeholder="بدون سقف" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('market.index') }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($listings->isEmpty())
        <x-empty-state icon="money" title="هنوز آگهی‌ای در بازارچه نیست"
            description="اولین کارگاهی باشید که الگویش را برای فروش می‌گذارد.">
            <x-slot:action>
                <x-button href="{{ route('market.sales') }}" icon="plus">گذاشتن آگهی</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($listings as $listing)
                <a href="{{ route('market.show', $listing) }}"
                    class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
                    <div class="relative flex h-40 items-center justify-center border-b border-stone-100 bg-stone-50 p-3">
                        @if ($listing->previewValue('silhouette'))
                            <div class="max-h-full w-full [&>svg]:max-h-32 [&>svg]:w-full">
                                {!! $listing->previewValue('silhouette') !!}
                            </div>
                        @else
                            <x-icon name="scissors" class="h-10 w-10 text-stone-300" />
                        @endif

                        <span class="absolute bottom-2 start-2 rounded-full bg-white/90 px-2.5 py-1 text-xs font-bold text-brand-700 shadow-sm">
                            {{ $listing->priceLabel() }}
                        </span>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-stone-900">{{ $listing->title }}</h3>
                            @if ($listing->isOwnedBy($workshopId))
                                <x-badge color="clay">آگهی خودتان</x-badge>
                            @endif
                        </div>

                        @if ($listing->description)
                            <p class="line-clamp-2 text-xs leading-relaxed text-stone-600">{{ $listing->description }}</p>
                        @endif

                        <div class="flex flex-wrap gap-1.5">
                            <x-badge color="slate" icon="layers">
                                {{ \App\Support\Jalali::digits($listing->previewValue('piece_count', 0)) }} قطعه
                            </x-badge>
                            @if ($listing->garmentType)
                                <x-badge color="sky">{{ $listing->garmentType->name_fa }}</x-badge>
                            @endif
                            @if ($listing->sales_count > 0)
                                <x-badge color="emerald" icon="check">
                                    {{ \App\Support\Jalali::digits($listing->sales_count) }} فروش
                                </x-badge>
                            @endif
                        </div>

                        <p class="mt-auto pt-2 text-xs text-stone-500">
                            فروشنده: {{ $listing->sellerWorkshop?->name ?? 'کارگاه ناشناس' }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $listings->links() }}</div>
    @endif
</x-app-layout>
