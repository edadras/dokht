<x-app-layout title="مدیریت سامانه" wide>
    <x-page-header title="مدیریت سامانه" subtitle="داده‌های مشترک همه کارگاه‌ها و وضعیت کلی سامانه." />

    @include('admin.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-stat label="کارگاه‌ها" :value="\App\Support\Format::number($counts['workshops'])" icon="home" />
        <x-stat label="کاربران" :value="\App\Support\Format::number($counts['users'])" icon="users" color="sky" />
        <x-stat label="انواع پارچه" :value="\App\Support\Format::number($counts['fabric_types'])" icon="palette"
            color="clay" :href="route('admin.fabric-types.index')" />
        <x-stat label="انواع لباس" :value="\App\Support\Format::number($counts['garment_types'])" icon="shirt"
            color="amber" :href="route('admin.garment-types.index')" />
        <x-stat label="الگوهای پایه" :value="\App\Support\Format::number($counts['templates'])" icon="ruler"
            :href="route('admin.templates.index')" />
        <x-stat label="قواعد طراحی" :value="\App\Support\Format::number($counts['rules'])" icon="check-circle"
            color="emerald" />
        <x-stat label="راهنماها" :value="\App\Support\Format::number($counts['guides'])" icon="book"
            :href="route('admin.guides.index')" />
        <x-stat label="الگوهای کارگاه‌ها" :value="\App\Support\Format::number($counts['patterns'])" icon="scissors"
            color="sky" />
        <x-stat label="سفارش‌ها" :value="\App\Support\Format::number($counts['orders'])" icon="clipboard"
            color="emerald" />
    </div>

    <x-card title="تازه‌ترین کارگاه‌ها" icon="home" padding="p-0" class="mt-6">
        @if ($workshops->isEmpty())
            <p class="p-5 text-sm text-stone-500">هنوز کارگاهی ثبت نشده است.</p>
        @else
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">کارگاه</th>
                            <th class="px-4 py-3 text-start font-medium">مدیر</th>
                            <th class="px-4 py-3 text-start font-medium">اعضا</th>
                            <th class="px-4 py-3 text-start font-medium">مشتری</th>
                            <th class="px-4 py-3 text-start font-medium">الگو</th>
                            <th class="px-4 py-3 text-start font-medium">سفارش</th>
                            <th class="px-4 py-3 text-start font-medium">تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($workshops as $workshop)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">{{ $workshop->name }}</span>
                                    <span class="block text-xs text-stone-400" dir="ltr">{{ $workshop->slug }}</span>
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ $workshop->owner?->name ?? '—' }}
                                    @if ($workshop->owner)
                                        <span class="block text-xs text-stone-400"
                                            dir="ltr">{{ $workshop->owner->email }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($workshop->users_count) }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($workshop->customers_count) }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($workshop->patterns_count) }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($workshop->orders_count) }}</td>
                                <td class="px-4 py-3 text-stone-500">
                                    {{ \App\Support\Jalali::date($workshop->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
