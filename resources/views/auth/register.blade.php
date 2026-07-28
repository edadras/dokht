<x-guest-layout title="ساخت کارگاه">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-black">کارگاه خیاطی خودتان را بسازید</h1>
        <p class="mt-1 text-sm text-stone-500">فقط چند ثانیه وقت می‌برد؛ بقیه چیزها را بعداً کامل می‌کنید.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-field label="نام و نام خانوادگی" name="name" required>
            <x-input name="name" placeholder="مثلاً مریم احمدی" required autofocus autocomplete="name" />
        </x-field>

        <x-field label="نام کارگاه" name="workshop_name"
            hint="اگر خالی بگذارید، خودمان یک نام برایتان می‌گذاریم و بعداً قابل تغییر است.">
            <x-input name="workshop_name" placeholder="مثلاً خیاطی مریم" />
        </x-field>

        <x-field label="ایمیل" name="email" required>
            <x-input type="email" name="email" placeholder="you@example.com" required autocomplete="username"
                dir="ltr" class="text-left" />
        </x-field>

        <x-field label="شماره تماس" name="phone">
            <x-input name="phone" placeholder="۰۹۱۲۰۰۰۰۰۰۰" autocomplete="tel" />
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="رمز عبور" name="password" required hint="حداقل ۸ نویسه">
                <x-input type="password" name="password" required autocomplete="new-password" />
            </x-field>

            <x-field label="تکرار رمز عبور" name="password_confirmation" required>
                <x-input type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-field>
        </div>

        <x-button type="submit" size="lg" class="w-full">ساختن کارگاه</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        قبلاً ثبت‌نام کرده‌اید؟
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">وارد شوید</a>
    </p>
</x-guest-layout>
