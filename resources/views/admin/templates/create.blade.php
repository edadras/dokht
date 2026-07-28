<x-app-layout title="الگوی پایه تازه">
    <x-page-header title="الگوی پایه تازه" subtitle="بلوک استانداردی که کارگاه‌ها کار خود را با آن شروع می‌کنند."
        :back="route('admin.templates.index')" />

    @include('admin.partials.nav')

    @include('admin.templates._form', [
        'action' => route('admin.templates.store'),
        'method' => 'POST',
        'submit' => 'افزودن الگوی پایه',
    ])
</x-app-layout>
