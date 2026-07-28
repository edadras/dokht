@use('App\Support\Jalali')

<x-app-layout :title="'ویرایش سفارش '.$order->code">
    <x-page-header :title="'ویرایش سفارش '.Jalali::digits($order->code)" :subtitle="$order->title"
        :back="route('orders.show', $order)" />

    <form method="POST" action="{{ route('orders.update', $order) }}" class="mx-auto max-w-3xl space-y-5">
        @csrf
        @method('PUT')

        @include('orders._form')

        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-confirm-delete :action="route('orders.destroy', $order)" message="این سفارش حذف شود؟" label="حذف سفارش"
                size="md" />

            <div class="flex items-center gap-2">
                <x-button href="{{ route('orders.show', $order) }}" variant="ghost">انصراف</x-button>
                <x-button type="submit" icon="check">ذخیره</x-button>
            </div>
        </div>
    </form>
</x-app-layout>
