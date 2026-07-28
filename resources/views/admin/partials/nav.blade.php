@php
    // نوار بخش‌های پنل مدیریت؛ جدا از نوار کنار کارگاه است تا دو محیط قاطی نشود
    $tabs = [
        ['route' => 'admin.index', 'label' => 'نمای کلی', 'icon' => 'chart', 'pattern' => 'admin.index'],
        ['route' => 'admin.fabric-types.index', 'label' => 'بانک پارچه', 'icon' => 'palette', 'pattern' => 'admin.fabric-types.*'],
        ['route' => 'admin.garment-types.index', 'label' => 'انواع لباس', 'icon' => 'shirt', 'pattern' => 'admin.garment-types.*'],
        ['route' => 'admin.templates.index', 'label' => 'الگوهای پایه', 'icon' => 'ruler', 'pattern' => 'admin.templates.*'],
        ['route' => 'admin.guides.index', 'label' => 'آموزش', 'icon' => 'book', 'pattern' => 'admin.guides.*'],
    ];
@endphp

<div class="mb-6 overflow-x-auto thin-scroll no-print">
    <nav class="flex min-w-max items-center gap-1 rounded-2xl border border-stone-200 bg-white p-1.5">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['pattern']); @endphp

            <a href="{{ route($tab['route']) }}"
                @class([
                    'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                    'bg-brand-600 text-white' => $active,
                    'text-stone-600 hover:bg-stone-100' => ! $active,
                ])>
                <x-icon :name="$tab['icon']" class="h-4 w-4" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
