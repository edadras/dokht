<x-app-layout title="پارچه جدید">
    <x-page-header title="ثبت پارچه جدید" subtitle="یک پارچه پایه انتخاب کنید و نام و رنگ بدهید؛ همین."
        :back="route('fabrics.index')" />

    <x-alert type="tip" title="نیازی به وارد کردن مشخصات فنی نیست" class="mb-6">
        وزن، لختی، کشسانی و بقیه ویژگی‌ها از بانک پارچه خوانده می‌شود. اگر پارچه شما با نمونه بانک تفاوت داشت، از بخش
        «مشخصات پیشرفته» تغییرش دهید.
    </x-alert>

    @include('fabrics.partials.form')
</x-app-layout>
