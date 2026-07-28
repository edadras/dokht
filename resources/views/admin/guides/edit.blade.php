<x-app-layout :title="'ویرایش '.$guide->title">
    <x-page-header :title="'ویرایش '.$guide->title" :subtitle="$guide->scopeLabel()"
        :back="route('admin.guides.index')" />

    @include('admin.partials.nav')

    @include('admin.guides._form', [
        'action' => route('admin.guides.update', $guide),
        'method' => 'PUT',
        'submit' => 'ذخیره تغییرات',
    ])
</x-app-layout>
