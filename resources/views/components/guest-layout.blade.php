@props(['title' => null])

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gradient-to-bl from-brand-50 via-stone-50 to-clay-50">
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white">
                    <x-icon name="scissors" class="h-7 w-7" />
                </span>
                <span class="text-2xl font-black text-stone-900">{{ config('app.name') }}</span>
            </a>

            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                <x-flash />
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
