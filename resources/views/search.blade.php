@use('App\Support\Jalali')

@php
    $found = collect($groups)->sum(fn ($group) => count($group['items']));
@endphp

<x-app-layout title="جست‌وجو">
    <x-page-header title="جست‌وجو"
        :subtitle="$isSearching ? 'برای «'.$q.'» — '.Jalali::digits($found).' نتیجه' : 'در مشتری‌ها، سفارش‌ها، پروژه‌ها، الگوها و پارچه‌ها بگردید.'" />

    <form method="GET" action="{{ route('search') }}" class="mb-6 flex flex-wrap items-center gap-2">
        <div class="relative min-w-56 flex-1">
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 end-3 my-auto h-5 w-5 text-stone-400" />
            <input type="search" name="q" value="{{ $q }}" autofocus
                placeholder="نام مشتری، شماره تماس، کد سفارش، نام پارچه…"
                class="w-full rounded-xl border border-stone-300 bg-white py-3 pe-11 ps-4 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
        <x-button type="submit" icon="search">جست‌وجو</x-button>
    </form>

    @if ($isSearching && $found === 0)
        <x-empty-state icon="search" title="چیزی پیدا نشد"
            description="شاید نام را جور دیگری نوشته‌اید. می‌توانید بخشی از نام یا شماره را بنویسید." />
    @else
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($groups as $key => $group)
                @continue(count($group['items']) === 0)

                <x-card :title="$group['title']" :icon="$group['icon']"
                    :subtitle="Jalali::digits(count($group['items'])).' مورد'">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}"
                                    class="flex items-center gap-3 py-2.5 transition hover:text-brand-600">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">{{ $item['title'] }}</span>
                                        <span class="block truncate text-xs text-stone-500">{{ $item['meta'] }}</span>
                                    </span>
                                    <x-icon name="chevron-left" class="h-4 w-4 shrink-0 text-stone-300" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>
