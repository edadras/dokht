<x-app-layout :title="'ویرایش '.$template->name_fa">
    <x-page-header :title="'ویرایش '.$template->name_fa" :subtitle="$template->garmentType?->name_fa"
        :back="route('admin.templates.index')" />

    @include('admin.partials.nav')

    @include('admin.templates._form', [
        'action' => route('admin.templates.update', $template),
        'method' => 'PUT',
        'submit' => 'ذخیره تغییرات',
    ])
</x-app-layout>
