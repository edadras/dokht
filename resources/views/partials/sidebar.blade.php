@php
    $workshop = auth()->user()->workshop;
@endphp

<div class="flex items-center gap-3 border-b border-stone-100 px-5 py-4">
    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white">
        <x-icon name="scissors" class="h-6 w-6" />
    </span>
    <div class="min-w-0">
        <p class="truncate font-bold">{{ $workshop?->name ?? config('app.name') }}</p>
        <p class="text-xs text-stone-500">کارگاه خیاطی</p>
    </div>
</div>

<nav class="thin-scroll flex-1 space-y-6 overflow-y-auto px-3 py-4">
    <div class="space-y-1">
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">خانه</x-nav-link>
        <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')" icon="clipboard">
            سفارش‌ها
        </x-nav-link>
        <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')" icon="users">
            مشتری‌ها
        </x-nav-link>
    </div>

    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold text-stone-400">طراحی و تولید</p>
        <x-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')" icon="shirt">
            پروژه‌ها
        </x-nav-link>
        <x-nav-link :href="route('patterns.index')" :active="request()->routeIs('patterns.*')" icon="scissors">
            الگوها
        </x-nav-link>
        <x-nav-link :href="route('fabrics.index')" :active="request()->routeIs('fabrics.*')" icon="layers">
            پارچه‌های من
        </x-nav-link>
        <x-nav-link :href="route('materials.index')" :active="request()->routeIs('materials.*')" icon="box">
            متعلقات
        </x-nav-link>
    </div>

    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold text-stone-400">دانش و کتابخانه</p>
        <x-nav-link :href="route('fabric-bank.index')" :active="request()->routeIs('fabric-bank.*')" icon="palette">
            بانک پارچه
        </x-nav-link>
        <x-nav-link :href="route('library.index')" :active="request()->routeIs('library.*')" icon="grid">
            کتابخانه مدل‌ها
        </x-nav-link>
        <x-nav-link :href="route('guides.index')" :active="request()->routeIs('guides.*')" icon="book">
            آموزش
        </x-nav-link>
        <x-nav-link :href="route('assistant.index')" :active="request()->routeIs('assistant.*')" icon="sparkles">
            دستیار
        </x-nav-link>
    </div>

    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold text-stone-400">کارگاه</p>
        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="chart">
            گزارش‌ها
        </x-nav-link>
        <x-nav-link :href="route('rules.index')" :active="request()->routeIs('rules.*')" icon="check-circle">
            قواعد طراحی
        </x-nav-link>
        @if (auth()->user()->canAdministerWorkshop())
            <x-nav-link :href="route('workshop.edit')" :active="request()->routeIs('workshop.*')" icon="settings">
                تنظیمات
            </x-nav-link>
        @endif
        @if (auth()->user()->is_admin)
            <x-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')" icon="star">
                مدیریت سامانه
            </x-nav-link>
        @endif
    </div>
</nav>

<div class="border-t border-stone-100 p-3">
    <a href="{{ route('projects.create') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
        <x-icon name="plus" class="h-5 w-5" />
        شروع یک لباس تازه
    </a>
</div>
