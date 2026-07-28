<x-app-layout title="نوع لباس تازه">
    <x-page-header title="نوع لباس تازه" subtitle="مدلی تازه به فهرست مشترک انواع لباس اضافه کنید."
        :back="route('admin.garment-types.index')" />

    @include('admin.partials.nav')

    @include('admin.garment-types._form', [
        'action' => route('admin.garment-types.store'),
        'method' => 'POST',
        'submit' => 'افزودن نوع لباس',
    ])
</x-app-layout>
