@use('App\Support\Jalali')

<x-app-layout title="مشتری‌ها">
    <x-page-header title="مشتری‌ها" subtitle="دفتر مشتری‌های کارگاه و دفترچه اندازه هرکدام.">
        <x-slot:actions>
            <x-button href="{{ route('customers.create') }}" icon="plus">مشتری جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('customers.index') }}" class="mb-5 flex flex-wrap items-center gap-2">
        <div class="relative min-w-56 flex-1">
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 end-3 my-auto h-5 w-5 text-stone-400" />
            <input type="search" name="q" value="{{ $q }}" placeholder="جست‌وجو با نام یا شماره تماس…"
                class="w-full rounded-xl border border-stone-300 bg-white py-2.5 pe-10 ps-4 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
        <x-button type="submit" variant="secondary">جست‌وجو</x-button>
        @if ($q)
            <x-button href="{{ route('customers.index') }}" variant="ghost">پاک کردن</x-button>
        @endif
    </form>

    @if ($customers->isEmpty())
        <x-empty-state icon="users"
            :title="$q ? 'مشتری‌ای با این نام پیدا نشد' : 'هنوز مشتری‌ای ثبت نشده'"
            :description="$q
                ? 'شاید نام یا شماره را جور دیگری نوشته‌اید. می‌توانید همین حالا مشتری تازه ثبت کنید.'
                : 'برای ثبت یک مشتری فقط نام او کافی است؛ شماره تماس و بقیه اطلاعات را بعداً هم می‌توانید اضافه کنید.'">
            <x-slot:action>
                <x-button href="{{ route('customers.create') }}" icon="plus">مشتری جدید</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($customers as $customer)
                @php($lastSet = $customer->defaultMeasurementSet)

                <x-card padding="p-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">
                            {{ mb_substr($customer->name, 0, 1) }}
                        </span>

                        <div class="min-w-40 flex-1">
                            <a href="{{ route('customers.show', $customer) }}"
                                class="font-bold text-stone-900 hover:text-brand-600">{{ $customer->name }}</a>

                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                                @if ($customer->phone)
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="user" class="h-3.5 w-3.5" />
                                        {{ Jalali::digits($customer->phone) }}
                                    </span>
                                @endif
                                <span>{{ $customer->genderLabel() }}</span>
                                <span>{{ Jalali::digits($customer->orders_count) }} سفارش</span>
                                <span>{{ Jalali::digits($customer->measurement_sets_count) }} مجموعه اندازه</span>
                            </div>

                            @if ($lastSet)
                                <p class="mt-1.5 text-xs text-stone-600">
                                    <span class="text-stone-400">آخرین اندازه:</span>
                                    {{ $lastSet->summary() ?: 'بدون عدد' }}
                                    <span class="text-stone-400">({{ Jalali::date($lastSet->created_at) }})</span>
                                </p>
                            @else
                                <p class="mt-1.5 text-xs text-amber-700">هنوز اندازه‌ای ثبت نشده است.</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-button href="{{ route('orders.create', ['customer' => $customer->id]) }}" size="sm"
                                variant="soft" icon="plus">سفارش جدید</x-button>
                            <x-button href="{{ route('measurements.create', $customer) }}" size="sm"
                                variant="secondary" icon="ruler">اندازه جدید</x-button>
                            <x-button href="{{ route('customers.edit', $customer) }}" size="sm" variant="ghost"
                                icon="edit">ویرایش</x-button>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>
    @endif
</x-app-layout>
