@use('App\Models\Order')
@use('App\Support\Format')
@use('App\Support\Jalali')

@php
    $actions = [
        ['title' => 'سفارش جدید', 'text' => 'مشتری، عنوان و تاریخ تحویل', 'icon' => 'clipboard', 'url' => route('orders.create')],
        ['title' => 'مشتری جدید', 'text' => 'فقط نام و شماره تماس', 'icon' => 'users', 'url' => route('customers.create')],
        ['title' => 'پروژه جدید', 'text' => 'دوخت یک لباس از صفر', 'icon' => 'shirt', 'url' => route('projects.create')],
        ['title' => 'اندازه جدید', 'text' => 'شش عدد از دفتر مشتری‌ها', 'icon' => 'ruler', 'url' => route('customers.index')],
    ];
@endphp

<x-app-layout title="خانه" wide>
    {{-- سلام و تاریخ امروز --}}
    <div class="mb-6">
        <p class="text-sm text-stone-500">
            {{ Jalali::weekday($today) }}، {{ Jalali::date($today, true) }}
        </p>
        <h1 class="mt-1 text-2xl font-black text-stone-900">
            سلام، {{ $workshop?->name ?? 'خوش آمدید' }}
        </h1>
        <p class="mt-1 text-sm text-stone-500">
            @if ($isFirstRun)
                کارگاه شما تازه ساخته شده است؛ بیایید با سه کار کوچک شروع کنیم.
            @else
                خلاصه کار امروز کارگاه در یک نگاه.
            @endif
        </p>
    </div>

    @if ($isFirstRun)
        {{-- راهنمای نخستین روز --}}
        <x-card class="mb-6" title="سه گام برای شروع" icon="sparkles"
            subtitle="ترتیب پیشنهادی؛ هر گام یک دقیقه بیشتر وقت نمی‌گیرد.">
            <ol class="space-y-3">
                @foreach ([['مشتری‌تان را ثبت کنید', 'برای شروع فقط نام او لازم است.', route('customers.create'), 'مشتری جدید', 'users'], ['اندازه‌هایش را بگیرید', 'شش عدد: قد، دور سینه، دور کمر، دور باسن، عرض سرشانه و قد آستین.', route('customers.index'), 'رفتن به دفتر مشتری‌ها', 'ruler'], ['نخستین سفارش را بسازید', 'عنوان کار و تاریخ تحویل؛ بعد کارت آن روی تخته تولید می‌آید.', route('orders.create'), 'سفارش جدید', 'clipboard']] as $i => $step)
                    <li class="flex flex-wrap items-center gap-3 rounded-xl border border-stone-200 p-4">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                            {{ Jalali::digits($i + 1) }}
                        </span>
                        <div class="min-w-40 flex-1">
                            <p class="font-bold text-stone-800">{{ $step[0] }}</p>
                            <p class="text-xs text-stone-500">{{ $step[1] }}</p>
                        </div>
                        <x-button href="{{ $step[2] }}" size="sm" :icon="$step[4]">{{ $step[3] }}</x-button>
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif

    {{-- کارهای پرکاربرد --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($actions as $action)
            <a href="{{ $action['url'] }}"
                class="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow">
                <span
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                    <x-icon :name="$action['icon']" class="h-6 w-6" />
                </span>
                <span class="min-w-0">
                    <span class="block font-bold text-stone-900">{{ $action['title'] }}</span>
                    <span class="block text-xs text-stone-500">{{ $action['text'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- آمار --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-stat label="سفارش‌های باز" :value="Jalali::digits($stats['open'])" icon="clipboard"
            :href="route('orders.index')" />
        <x-stat label="تحویل این هفته" :value="Jalali::digits($stats['dueThisWeek'])" icon="calendar" color="sky"
            :href="route('orders.index', ['view' => 'list'])" />
        <x-stat label="دیرکرد" :value="Jalali::digits($stats['overdue'])" icon="alert"
            :color="$stats['overdue'] > 0 ? 'rose' : 'emerald'" :href="route('orders.index', ['view' => 'list', 'overdue' => 1])" />
        <x-stat label="درآمد {{ $month['label'] }}" :value="Format::money($stats['income'])" icon="money"
            color="emerald" :href="route('reports.index')" />
        <x-stat label="تعداد مشتری" :value="Jalali::digits($stats['customers'])" icon="users"
            :href="route('customers.index')" />
        <x-stat label="پارچه موجود" :value="Format::number($stats['fabricMeters'], 1).' متر'" icon="layers" color="clay"
            :href="route('fabrics.index')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- کارهای امروز و این هفته --}}
            <x-card title="کارهای امروز و این هفته" icon="clock"
                subtitle="سفارش‌هایی که تاریخ تحویلشان نزدیک است یا گذشته.">
                <x-slot:actions>
                    <x-button href="{{ route('orders.index') }}" size="sm" variant="ghost">تخته تولید</x-button>
                </x-slot:actions>

                @if ($tasks->isEmpty())
                    <p class="py-4 text-center text-sm text-stone-500">
                        کاری برای این هفته در صف نیست. نفسی تازه کنید.
                    </p>
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($tasks as $task)
                            <li class="flex flex-wrap items-center gap-3 py-3">
                                <div class="min-w-40 flex-1">
                                    <a href="{{ route('orders.show', $task) }}"
                                        class="font-semibold text-stone-900 hover:text-brand-600">{{ $task->title }}</a>
                                    <p class="mt-0.5 text-xs text-stone-500">
                                        {{ $task->customer?->name ?? 'بدون مشتری' }}
                                        •
                                        <span class="{{ $task->isOverdue() ? 'font-semibold text-rose-600' : '' }}">
                                            {{ Jalali::date($task->due_date) }}
                                            @if ($task->isOverdue())
                                                (دیرکرد)
                                            @else
                                                ({{ Jalali::ago($task->due_date) }})
                                            @endif
                                        </span>
                                        @if ($task->remainingAmount() > 0)
                                            • مانده {{ Format::money($task->remainingAmount()) }}
                                        @endif
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('orders.status', $task) }}"
                                    class="flex items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="task-status-{{ $task->id }}">وضعیت</label>
                                    <select id="task-status-{{ $task->id }}" name="status"
                                        class="rounded-lg border-stone-200 bg-stone-50 py-1 text-xs focus:border-brand-500 focus:ring-brand-400">
                                        @foreach (Order::STATUSES as $value => $meta)
                                            <option value="{{ $value }}" @selected($task->status === $value)>
                                                {{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                        class="rounded-lg bg-stone-100 p-1.5 text-stone-600 transition hover:bg-brand-600 hover:text-white"
                                        title="ثبت وضعیت">
                                        <x-icon name="check" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- پروژه‌های در جریان --}}
            <x-card title="پروژه‌های در جریان" icon="shirt">
                <x-slot:actions>
                    <x-button href="{{ route('projects.index') }}" size="sm" variant="ghost">همه پروژه‌ها</x-button>
                </x-slot:actions>

                @if ($projects->isEmpty())
                    <p class="py-4 text-center text-sm text-stone-500">پروژه‌ای در جریان نیست.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($projects as $project)
                            <a href="{{ route('projects.show', $project) }}"
                                class="block rounded-xl border border-stone-200 p-3 transition hover:border-brand-300">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-stone-800">{{ $project->name }}</p>
                                    <span class="text-xs text-stone-500">
                                        {{ $project->customer?->name ?? 'بدون مشتری' }}
                                    </span>
                                    <x-badge class="ms-auto" color="brand">{{ $project->stepTitle() }}</x-badge>
                                </div>
                                <x-meter class="mt-2" :value="$project->progressPercent()" label="پیشرفت" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            {{-- خلاصه تخته تولید --}}
            <x-card title="تخته تولید" icon="grid">
                <ul class="space-y-2">
                    @foreach (Order::STATUSES as $status => $meta)
                        <li>
                            <a href="{{ route('orders.index', ['view' => 'list', 'status' => $status]) }}"
                                class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm transition hover:bg-stone-50">
                                <x-badge :color="$meta['color']">{{ $meta['label'] }}</x-badge>
                                <span class="ms-auto font-bold text-stone-800">
                                    {{ Jalali::digits($statusCounts[$status] ?? 0) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{-- کم‌موجودی پارچه --}}
            <x-card title="کم‌موجودی پارچه" icon="layers" subtitle="پارچه‌هایی که زیر ۲ متر مانده‌اند.">
                @if ($lowFabrics->isEmpty())
                    <p class="text-sm text-stone-500">همه پارچه‌ها موجودی کافی دارند.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($lowFabrics as $fabric)
                            <li class="flex items-center gap-2 text-sm">
                                <span class="h-3 w-3 shrink-0 rounded-full border border-stone-200"
                                    style="background-color: {{ $fabric->color_hex ?: '#e7e5e4' }}"></span>
                                <a href="{{ route('fabrics.show', $fabric) }}" class="truncate hover:text-brand-600">
                                    {{ $fabric->displayName() }}
                                </a>
                                <span class="ms-auto shrink-0 font-semibold text-amber-700">
                                    {{ Format::number($fabric->stock_meters, 1) }} متر
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <x-button class="mt-4 w-full" href="{{ route('fabrics.index') }}" size="sm" variant="secondary">
                        مدیریت پارچه‌ها
                    </x-button>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
