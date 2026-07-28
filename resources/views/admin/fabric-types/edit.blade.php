<x-app-layout :title="'ویرایش '.$type->name_fa">
    <x-page-header :title="'ویرایش '.$type->name_fa" :subtitle="$type->compositionLabel() ?: 'شناسنامه این پارچه را کامل کنید.'"
        :back="route('admin.fabric-types.index')" />

    @include('admin.partials.nav')

    @include('admin.fabric-types._form', [
        'action' => route('admin.fabric-types.update', $type),
        'method' => 'PUT',
        'submit' => 'ذخیره تغییرات',
    ])
</x-app-layout>
