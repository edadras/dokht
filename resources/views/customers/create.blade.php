@php($customer = new App\Models\Customer(['gender' => 'female']))

<x-app-layout title="مشتری جدید">
    <x-page-header title="مشتری جدید" subtitle="برای شروع فقط نام مشتری را بنویسید."
        :back="route('customers.index')" />

    <form method="POST" action="{{ route('customers.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf

        <x-card>
            @include('customers._form', ['customer' => $customer])
        </x-card>

        <div class="flex items-center justify-end gap-2">
            <x-button href="{{ route('customers.index') }}" variant="ghost">انصراف</x-button>
            <x-button type="submit" icon="check">ثبت مشتری</x-button>
        </div>
    </form>
</x-app-layout>
