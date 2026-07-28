<x-app-layout title="راهنمای تازه">
    <x-page-header title="راهنمای تازه" subtitle="یک درس کوتاه برای بخش آموزش بنویسید."
        :back="route('admin.guides.index')" />

    @include('admin.partials.nav')

    @include('admin.guides._form', [
        'action' => route('admin.guides.store'),
        'method' => 'POST',
        'submit' => 'افزودن راهنما',
    ])
</x-app-layout>
