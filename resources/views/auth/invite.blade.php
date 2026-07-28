<x-guest-layout title="دعوت به کارگاه">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-black">دعوت به {{ $invite->workshop->name }}</h1>
        <p class="mt-1 text-sm text-stone-500">
            شما به‌عنوان «{{ $invite->roleLabel() }}» به این کارگاه دعوت شده‌اید.
        </p>
    </div>

    <form method="POST" action="{{ route('invites.accept', $invite->token) }}" class="space-y-4">
        @csrf

        <x-field label="ایمیل">
            <x-input :value="$invite->email" disabled dir="ltr" class="bg-stone-50 text-left" />
        </x-field>

        <x-field label="نام و نام خانوادگی" name="name" required>
            <x-input name="name" required autofocus />
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="رمز عبور" name="password" required hint="حداقل ۸ نویسه">
                <x-input type="password" name="password" required autocomplete="new-password" />
            </x-field>

            <x-field label="تکرار رمز عبور" name="password_confirmation" required>
                <x-input type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-field>
        </div>

        <x-button type="submit" size="lg" class="w-full">پیوستن به کارگاه</x-button>
    </form>
</x-guest-layout>
