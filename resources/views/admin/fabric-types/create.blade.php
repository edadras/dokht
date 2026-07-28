<x-app-layout title="پارچه تازه">
    <x-page-header title="پارچه تازه در بانک" subtitle="یک نوع پارچه سراسری بسازید تا همه کارگاه‌ها از آن استفاده کنند."
        :back="route('admin.fabric-types.index')" />

    @include('admin.partials.nav')

    @include('admin.fabric-types._form', [
        'action' => route('admin.fabric-types.store'),
        'method' => 'POST',
        'submit' => 'افزودن پارچه',
    ])
</x-app-layout>
