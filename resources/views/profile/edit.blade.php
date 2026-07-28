<x-app-layout title="حساب من">
    <x-page-header title="حساب من" subtitle="نام، ایمیل و رمز عبور خود را اینجا تغییر دهید." />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="اطلاعات شخصی" icon="user">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-field label="نام و نام خانوادگی" name="name" required>
                    <x-input name="name" :value="$user->name" required />
                </x-field>

                <x-field label="ایمیل" name="email" required>
                    <x-input type="email" name="email" :value="$user->email" required dir="ltr" class="text-left" />
                </x-field>

                <x-field label="شماره تماس" name="phone">
                    <x-input name="phone" :value="$user->phone" />
                </x-field>

                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-stone-500">نقش شما: {{ $user->roleLabel() }}</p>
                    <x-button type="submit">ذخیره</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="تغییر رمز عبور" icon="settings">
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field label="رمز فعلی" name="current_password" required>
                    <x-input type="password" name="current_password" required autocomplete="current-password" />
                </x-field>

                <x-field label="رمز جدید" name="password" required hint="حداقل ۸ نویسه">
                    <x-input type="password" name="password" required autocomplete="new-password" />
                </x-field>

                <x-field label="تکرار رمز جدید" name="password_confirmation" required>
                    <x-input type="password" name="password_confirmation" required autocomplete="new-password" />
                </x-field>

                <div class="flex justify-end">
                    <x-button type="submit">تغییر رمز</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
