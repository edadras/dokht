<x-guest-layout title="بازیابی رمز عبور">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-black">رمزتان را فراموش کرده‌اید؟</h1>
        <p class="mt-1 text-sm text-stone-500">
            ایمیلی که با آن ثبت‌نام کرده‌اید را بنویسید؛ لینک ساختن رمز تازه برایتان می‌فرستیم.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-field label="ایمیل" name="email" required>
            <x-input type="email" name="email" :value="old('email')" required autofocus autocomplete="username"
                dir="ltr" class="text-left" />
        </x-field>

        <x-button type="submit" size="lg" class="w-full">فرستادن لینک بازیابی</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        رمزتان یادتان آمد؟
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">بازگشت به ورود</a>
    </p>
</x-guest-layout>
