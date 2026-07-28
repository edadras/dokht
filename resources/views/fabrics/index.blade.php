<x-app-layout title="پارچه‌های من">
    <x-page-header title="پارچه‌های من" subtitle="انبار پارچه کارگاه با شناسنامه کامل هر پارچه.">
        <x-slot:actions>
            <x-button href="{{ route('fabric-bank.index') }}" variant="secondary" icon="palette">بانک پارچه</x-button>
            <x-button href="{{ route('fabrics.create') }}" icon="plus">پارچه جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="تعداد پارچه" :value="\App\Support\Format::number($fabrics->total())" icon="layers" />
        <x-stat label="مجموع موجودی" :value="\App\Support\Jalali::digits(number_format($totalMeters, 1)).' متر'" icon="ruler"
            color="emerald" />
        <x-stat label="بانک پارچه" value="مرور و افزودن" icon="palette" color="clay" hint="با یک کلیک اضافه کنید"
            :href="route('fabric-bank.index')" />
    </div>

    <form method="GET" action="{{ route('fabrics.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="جست‌وجو" name="q" class="min-w-56 flex-1">
            <x-input name="q" :value="$q" placeholder="نام یا رنگ پارچه…" />
        </x-field>

        <x-field label="پارچه پایه" name="type" class="min-w-48">
            <x-select name="type" :options="$typeOptions" :selected="$selectedType" placeholder="همه" />
        </x-field>

        <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
    </form>

    @if ($fabrics->isEmpty())
        <x-empty-state icon="layers" title="هنوز پارچه‌ای ثبت نکرده‌اید"
            description="ساده‌ترین راه این است که از بانک پارچه یک پارچه پایه انتخاب کنید؛ همه مشخصات فنی خودکار پر می‌شود.">
            <x-slot:action>
                <div class="flex flex-wrap justify-center gap-2">
                    <x-button href="{{ route('fabric-bank.index') }}" icon="palette">رفتن به بانک پارچه</x-button>
                    <x-button href="{{ route('fabrics.create') }}" variant="secondary" icon="plus">ثبت دستی</x-button>
                </div>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($fabrics as $fabric)
                @php($profile = $fabric->profile())

                <a href="{{ route('fabrics.show', $fabric) }}"
                    class="flex gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow">
                    @include('fabrics.partials.swatch', ['fabric' => $fabric])

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-stone-900">{{ $fabric->name }}</p>
                        <p class="truncate text-xs text-stone-500">
                            {{ $fabric->typeName() }}@if ($fabric->color) — {{ $fabric->color }} @endif
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <x-badge color="brand">{{ $profile->drapeLabel() }}</x-badge>
                            <x-badge>{{ \App\Support\Format::number($profile->get('weight_gsm')) }} گرم</x-badge>
                            @if ($fabric->width_cm)
                                <x-badge>عرض {{ \App\Support\Jalali::digits((int) $fabric->width_cm) }}</x-badge>
                            @endif
                            @if ($fabric->surface_pattern !== 'plain')
                                <x-badge color="violet">{{ $fabric->surfacePatternLabel() }}</x-badge>
                            @endif
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="font-semibold text-stone-700">
                                موجودی {{ \App\Support\Jalali::digits(number_format((float) $fabric->stock_meters, 1)) }}
                                متر
                            </span>
                            <span class="text-stone-500">
                                {{ $fabric->price_per_meter ? \App\Support\Format::money($fabric->price_per_meter) : '—' }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $fabrics->links() }}</div>
    @endif
</x-app-layout>
