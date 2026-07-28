<x-app-layout title="سفارش جدید">
    <x-page-header title="سفارش جدید" subtitle="کد سفارش خودکار ساخته می‌شود." :back="route('orders.index')" />

    <form method="POST" action="{{ route('orders.store') }}" class="mx-auto max-w-3xl space-y-5">
        @csrf

        @include('orders._form')

        <div class="flex items-center justify-end gap-2">
            <x-button href="{{ route('orders.index') }}" variant="ghost">انصراف</x-button>
            <x-button type="submit" icon="check">ثبت سفارش</x-button>
        </div>
    </form>
</x-app-layout>
