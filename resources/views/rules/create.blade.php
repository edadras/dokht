<x-app-layout title="قاعده جدید">
    <x-page-header title="ساخت قاعده جدید"
        subtitle="یک تجربه کاری خودتان را به سامانه یاد بدهید تا از این به بعد خودش یادآوری کند."
        :back="route('rules.index')" />

    <x-alert type="tip" title="مثال" class="mb-6">
        «اگر <b>شفافیت پارچه</b> بیشتر از <b>۰.۶</b> بود، <b>پیشنهاد آستر</b> بده.» — همین سه انتخاب کافی است.
    </x-alert>

    @include('rules.partials.form')
</x-app-layout>
