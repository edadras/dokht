<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — سامانه کارگاه خیاطی</title>
    <meta name="description" content="ثبت اندازه مشتری، انتخاب پارچه، ساخت الگو، تست سه‌بعدی لباس، چیدمان برش و کارت فنی؛ همه در یک جا.">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-stone-50">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4">
            <span class="flex items-center gap-2">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white">
                    <x-icon name="scissors" class="h-6 w-6" />
                </span>
                <span class="text-xl font-black">{{ config('app.name') }}</span>
            </span>

            <nav class="ms-auto flex items-center gap-2">
                <x-button href="{{ route('login') }}" variant="ghost">ورود</x-button>
                <x-button href="{{ route('register') }}" icon="plus">ساخت کارگاه</x-button>
            </nav>
        </div>
    </header>

    <main>
        <section class="mx-auto max-w-6xl px-4 py-16 text-center">
            <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                <x-icon name="sparkles" class="h-4 w-4" />
                از اندازه‌گیری تا برش، بدون دفتر و کاغذ
            </p>

            <h1 class="text-3xl font-black leading-tight text-stone-900 sm:text-5xl">
                کارگاه خیاطی شما، مرتب و هوشمند
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-stone-600">
                اندازه مشتری‌ها را ثبت کنید، پارچه را انتخاب کنید و سامانه خودش الگو را می‌سازد،
                رفتار پارچه را روی مانکن نشان می‌دهد، بهترین چیدمان برش را پیدا می‌کند و کارت فنی می‌دهد.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <x-button href="{{ route('register') }}" size="lg" icon="plus">همین حالا شروع کنید</x-button>
                <x-button href="{{ route('login') }}" size="lg" variant="secondary">ورود به کارگاه</x-button>
            </div>

            <p class="mt-4 text-xs text-stone-400">
                {{ \App\Support\Jalali::digits($fabricCount) }} نوع پارچه و
                {{ \App\Support\Jalali::digits($garmentCount) }} مدل لباس از پیش آماده است.
            </p>
        </section>

        <section class="mx-auto grid max-w-6xl gap-4 px-4 pb-20 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['icon' => 'users', 'title' => 'مشتری و اندازه', 'text' => 'با شش عدد ساده شروع کنید؛ بقیه اندازه‌ها خودکار تخمین زده می‌شود.'],
                ['icon' => 'layers', 'title' => 'شناخت پارچه', 'text' => 'لختی، کشسانی، شفافیت و رفتار پارچه در برش برای هر پارچه ثبت می‌شود.'],
                ['icon' => 'scissors', 'title' => 'ساخت الگو', 'text' => 'الگوی پایه بر اساس اندازه‌های واقعی مشتری ساخته و قابل ویرایش می‌شود.'],
                ['icon' => 'cube', 'title' => 'تست سه‌بعدی', 'text' => 'لباس روی مانکن با اندازه‌های همان مشتری، با رفتار همان پارچه.'],
                ['icon' => 'grid', 'title' => 'چیدمان برش', 'text' => 'مصرف پارچه، درصد دورریز و رعایت جهت پارچه و تطبیق راه‌راه.'],
                ['icon' => 'file', 'title' => 'کارت فنی و خروجی', 'text' => 'الگوی قابل چاپ، فایل SVG و DXF، صورت مواد و دستور دوخت.'],
            ] as $feature)
                <div class="rounded-2xl border border-stone-200 bg-white p-6">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon :name="$feature['icon']" class="h-6 w-6" />
                    </span>
                    <h2 class="mt-4 font-bold">{{ $feature['title'] }}</h2>
                    <p class="mt-1 text-sm leading-6 text-stone-600">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </section>
    </main>

    <footer class="border-t border-stone-200 bg-white py-8 text-center text-sm text-stone-500">
        {{ config('app.name') }} — سامانه مدیریت کارگاه خیاطی
    </footer>
</body>

</html>
