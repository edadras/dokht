<x-app-layout :title="'ویرایش '.$type->name_fa">
    <x-page-header :title="'ویرایش '.$type->name_fa" :subtitle="$type->categoryLabel()"
        :back="route('admin.garment-types.index')" />

    @include('admin.partials.nav')

    @include('admin.garment-types._form', [
        'action' => route('admin.garment-types.update', $type),
        'method' => 'PUT',
        'submit' => 'ذخیره تغییرات',
    ])
</x-app-layout>
