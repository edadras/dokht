<x-app-layout :title="'ویرایش اندازه‌های '.$customer->name">
    <x-page-header :title="'ویرایش اندازه‌های '.$customer->name" :subtitle="$set->displayTitle()"
        :back="route('customers.show', $customer)" />

    <form method="POST" action="{{ route('measurements.update', $set) }}" class="space-y-5">
        @csrf
        @method('PUT')

        @include('measurements._form', ['customer' => $customer, 'set' => $set, 'values' => $values, 'previous' => $previous])

        <div class="sticky bottom-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white/95 p-3 shadow-lg backdrop-blur">
            <x-confirm-delete :action="route('measurements.destroy', $set)" message="این مجموعه اندازه حذف شود؟" />

            <div class="flex items-center gap-2">
                <x-button href="{{ route('customers.show', $customer) }}" variant="ghost">انصراف</x-button>
                <x-button type="submit" icon="check">ذخیره تغییرها</x-button>
            </div>
        </div>
    </form>
</x-app-layout>
