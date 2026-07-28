@php
    use App\Support\Format;
@endphp

<x-app-layout title="بانک پارچه">
    <x-page-header title="بانک پارچه"
        :subtitle="'شناسنامه کامل '.Format::number($total).' پارچه؛ هر کدام را با یک کلیک به انبار خودتان اضافه کنید.'">
        <x-slot:actions>
            <x-button href="{{ route('fabrics.index') }}" variant="secondary" icon="layers">پارچه‌های من</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('fabric-bank.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="جست‌وجو" name="q" class="min-w-56 flex-1">
            <x-input name="q" :value="$q" placeholder="مثلاً حریر، جین، کرپ…" />
        </x-field>

        <x-field label="دسته الیاف" name="category" class="min-w-44">
            <x-select name="category" :options="$categories" :selected="$category" placeholder="همه" />
        </x-field>

        <x-field label="نوع بافت" name="family" class="min-w-44">
            <x-select name="family" :options="$families" :selected="$family" placeholder="همه" />
        </x-field>

        <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
    </form>

    @if ($types->isEmpty())
        <x-empty-state icon="palette" title="پارچه‌ای پیدا نشد"
            description="عبارت جست‌وجو یا فیلترها را تغییر دهید.">
            <x-slot:action>
                <x-button href="{{ route('fabric-bank.index') }}" variant="secondary" icon="refresh">
                    نمایش همه پارچه‌ها
                </x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($types as $type)
                @php($profile = $type->fabricProfile())

                <div class="flex flex-col rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('fabric-bank.show', $type) }}"
                                class="block truncate font-bold text-stone-900 hover:text-brand-600">
                                {{ $type->name_fa }}
                            </a>
                            <p class="truncate text-xs text-stone-500" dir="ltr">{{ $type->name_en }}</p>
                        </div>
                        <x-badge color="clay">{{ $type->categoryLabel() }}</x-badge>
                    </div>

                    <p class="mt-2 line-clamp-2 text-xs text-stone-600">{{ $type->description }}</p>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <x-badge color="brand">{{ $profile->drapeLabel() }}</x-badge>
                        <x-badge>{{ Format::number($profile->get('weight_gsm')) }} گرم</x-badge>
                        @if ($type->familyLabel())
                            <x-badge color="violet">{{ $type->familyLabel() }}</x-badge>
                        @endif
                        @if ($profile->isStretchy())
                            <x-badge color="emerald">کشسانی {{ Format::percent($profile->maxStretch()) }}</x-badge>
                        @endif
                        @if ($profile->isSheer())
                            <x-badge color="amber">شفاف</x-badge>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-2 border-t border-stone-100 pt-3">
                        <form method="POST" action="{{ route('fabric-bank.add', $type) }}" class="flex-1">
                            @csrf
                            <x-button type="submit" size="sm" icon="plus" class="w-full">افزودن به پارچه‌های من</x-button>
                        </form>

                        <x-button href="{{ route('fabric-bank.show', $type) }}" variant="ghost" size="sm" icon="eye">
                            جزئیات
                        </x-button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
