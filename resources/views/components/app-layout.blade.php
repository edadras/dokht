@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="min-h-screen">
    <div x-data="{ sidebar: false }" class="min-h-screen lg:flex">
        {{-- نوار کنار: در موبایل کشویی --}}
        <div x-cloak x-show="sidebar" @click="sidebar = false"
            class="fixed inset-0 z-30 bg-stone-900/40 backdrop-blur-sm lg:hidden"></div>

        <aside x-cloak
            :class="sidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 right-0 z-40 flex w-72 flex-col border-s border-stone-200 bg-white transition-transform lg:static lg:translate-x-0 no-print">
            @include('partials.sidebar')
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-stone-200 bg-white/90 backdrop-blur no-print">
                <div class="flex items-center gap-3 px-4 py-3 lg:px-8">
                    <button type="button" @click="sidebar = true"
                        class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 lg:hidden" aria-label="نمایش منو">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    <form action="{{ route('search') }}" method="GET" class="relative hidden flex-1 sm:block">
                        <x-icon name="search"
                            class="pointer-events-none absolute inset-y-0 end-3 my-auto h-5 w-5 text-stone-400" />
                        <input type="search" name="q" value="{{ request('q') }}"
                            placeholder="جست‌وجوی مشتری، سفارش، الگو یا پارچه…"
                            class="w-full max-w-lg rounded-xl border-stone-200 bg-stone-50 py-2 pe-10 ps-4 text-sm placeholder:text-stone-400 focus:border-brand-400 focus:bg-white focus:ring-brand-400">
                    </form>

                    <div class="ms-auto flex items-center gap-2">
                        <x-button href="{{ route('projects.create') }}" size="sm" icon="plus">پروژه جدید</x-button>

                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" type="button"
                                class="flex items-center gap-2 rounded-xl p-1.5 hover:bg-stone-100">
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                                    {{ auth()->user()->initials() }}
                                </span>
                                <x-icon name="chevron-down" class="h-4 w-4 text-stone-400" />
                            </button>

                            <div x-cloak x-show="open" @click.outside="open = false"
                                x-transition.origin.top.left
                                class="absolute end-0 mt-2 w-56 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-lg">
                                <div class="border-b border-stone-100 px-4 py-3">
                                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-stone-500">{{ auth()->user()->roleLabel() }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}"
                                    class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-stone-50">
                                    <x-icon name="user" class="h-4 w-4 text-stone-400" /> حساب من
                                </a>
                                <a href="{{ route('workshop.edit') }}"
                                    class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-stone-50">
                                    <x-icon name="settings" class="h-4 w-4 text-stone-400" /> تنظیمات کارگاه
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">
                                        <x-icon name="logout" class="h-4 w-4" /> خروج
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8">
                <div class="{{ $wide ? '' : 'mx-auto max-w-6xl' }}">
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>

            <footer class="px-4 py-6 text-center text-xs text-stone-400 lg:px-8 no-print">
                {{ config('app.name') }} — سامانه کارگاه خیاطی
            </footer>
        </div>
    </div>
</body>

</html>
