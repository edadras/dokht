<x-app-layout :title="'اندازه‌گیری '.$customer->name">
    <x-page-header :title="'اندازه‌گیری '.$customer->name"
        subtitle="شش عدد بگیرید و ذخیره کنید؛ همین برای ساخت الگو کافی است."
        :back="route('customers.show', $customer)" />

    <form method="POST" action="{{ route('measurements.store', $customer) }}" class="space-y-5">
        @csrf

        @include('measurements._form', ['customer' => $customer, 'set' => $set, 'values' => $values, 'previous' => $previous])

        <div class="sticky bottom-4 flex flex-wrap items-center justify-end gap-2 rounded-2xl border border-stone-200 bg-white/95 p-3 shadow-lg backdrop-blur">
            <p class="me-auto text-xs text-stone-500">اندازه‌های خالی خودکار تخمین زده می‌شود.</p>
            <x-button href="{{ route('customers.show', $customer) }}" variant="ghost">انصراف</x-button>
            <x-button type="submit" icon="check">ذخیره اندازه‌ها</x-button>
        </div>
    </form>
</x-app-layout>
