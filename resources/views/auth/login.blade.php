<x-guest-layout title="ورود">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-black">ورود به کارگاه</h1>
        <p class="mt-1 text-sm text-stone-500">خوش آمدید؛ کارتان را از همان‌جا که رها کردید ادامه دهید.</p>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-field label="ایمیل" name="email" required>
            <x-input type="email" name="email" required autofocus autocomplete="username" dir="ltr"
                class="text-left" />
        </x-field>

        <x-field label="رمز عبور" name="password" required>
            <x-input type="password" name="password" required autocomplete="current-password" />
        </x-field>

        <div class="text-start">
            <a href="{{ route('password.request') }}" class="text-sm text-brand-600 hover:underline">
                رمزتان را فراموش کرده‌اید؟
            </a>
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" value="1"
                class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
            مرا به خاطر بسپار
        </label>

        <x-button type="submit" size="lg" class="w-full">ورود</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        کارگاه ندارید؟
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">همین حالا بسازید</a>
    </p>
</x-guest-layout>
