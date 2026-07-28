<x-app-layout title="ثبت متعلقات">
    <x-page-header title="ثبت متعلقات" subtitle="یک قلم تازه به انبار متعلقات اضافه کنید."
        :back="route('materials.index')" />

    <x-card title="مشخصات" icon="box" class="mx-auto max-w-2xl">
        @include('materials.partials.form')
    </x-card>
</x-app-layout>
