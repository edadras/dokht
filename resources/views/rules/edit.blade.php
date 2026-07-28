<x-app-layout title="ویرایش قاعده">
    <x-page-header :title="'ویرایش «'.$rule->name.'»'" :subtitle="'دامنه: '.$rule->scopeLabel()" :back="route('rules.index')">
        <x-slot:actions>
            <x-confirm-delete :action="route('rules.destroy', $rule)" :message="'قاعده «'.$rule->name.'» حذف شود؟'" />
        </x-slot:actions>
    </x-page-header>

    @include('rules.partials.form')
</x-app-layout>
