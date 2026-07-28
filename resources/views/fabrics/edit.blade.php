<x-app-layout title="ویرایش پارچه">
    <x-page-header :title="'ویرایش «'.$fabric->name.'»'" subtitle="هر چیزی را که لازم است تغییر دهید."
        :back="route('fabrics.show', $fabric)">
        <x-slot:actions>
            <x-confirm-delete :action="route('fabrics.destroy', $fabric)"
                message="این پارچه حذف شود؟ پروژه‌هایی که از آن استفاده کرده‌اند بدون پارچه می‌مانند." label="حذف پارچه" />
        </x-slot:actions>
    </x-page-header>

    @include('fabrics.partials.form')
</x-app-layout>
