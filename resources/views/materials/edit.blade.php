<x-app-layout title="ویرایش متعلقات">
    <x-page-header :title="'ویرایش «'.$material->name.'»'" :subtitle="$material->kindLabel()" :back="route('materials.index')">
        <x-slot:actions>
            <x-confirm-delete :action="route('materials.destroy', $material)" :message="'«'.$material->name.'» حذف شود؟'" />
        </x-slot:actions>
    </x-page-header>

    <x-card title="مشخصات" icon="box" class="mx-auto max-w-2xl">
        @include('materials.partials.form')
    </x-card>
</x-app-layout>
