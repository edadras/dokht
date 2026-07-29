<x-guest-layout title="ساختن رمز تازه">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-black">رمز تازه</h1>
        <p class="mt-1 text-sm text-stone-500">رمزی بگذارید که به یاد بسپارید؛ بعد از این با همین وارد می‌شوید.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field label="ایمیل" name="email" required>
            <x-input type="email" name="email" :value="old('email', $email)" required autocomplete="username"
                dir="ltr" class="text-left" />
        </x-field>

        <x-field label="رمز تازه" name="password" required>
            <x-input type="password" name="password" required autocomplete="new-password" />
        </x-field>

        <x-field label="تکرار رمز تازه" name="password_confirmation" required>
            <x-input type="password" name="password_confirmation" required autocomplete="new-password" />
        </x-field>

        <x-button type="submit" size="lg" class="w-full">ثبت رمز تازه</x-button>
    </form>
</x-guest-layout>
