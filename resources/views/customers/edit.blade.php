<x-app-layout :title="'ویرایش '.$customer->name">
    <x-page-header :title="'ویرایش '.$customer->name" subtitle="اطلاعات تماس و یادداشت‌های مشتری."
        :back="route('customers.show', $customer)" />

    <form method="POST" action="{{ route('customers.update', $customer) }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <x-card>
            @include('customers._form', ['customer' => $customer])
        </x-card>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-confirm-delete :action="route('customers.destroy', $customer)"
                message="این مشتری و دفترچه اندازه‌اش از فهرست حذف می‌شود. مطمئن هستید؟" label="حذف مشتری" size="md" />

            <div class="flex items-center gap-2">
                <x-button href="{{ route('customers.show', $customer) }}" variant="ghost">انصراف</x-button>
                <x-button type="submit" icon="check">ذخیره</x-button>
            </div>
        </div>
    </form>
</x-app-layout>
