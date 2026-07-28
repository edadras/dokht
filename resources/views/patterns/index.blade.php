<x-app-layout title="الگوها" wide>
    <x-page-header title="الگوها" subtitle="هر الگو با انتخاب یک مدل و یک مشتری ساخته می‌شود.">
        <x-slot:actions>
            <x-button href="{{ route('patterns.create') }}" icon="plus">الگوی جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('patterns.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="جست‌وجو" name="q" class="min-w-56 flex-1">
            <x-input name="q" :value="$term" placeholder="نام الگو…" />
        </x-field>

        <x-field label="نوع لباس" name="garment_type" class="min-w-44">
            <x-select name="garment_type" :options="$garmentTypes" :selected="request('garment_type')"
                placeholder="همه" />
        </x-field>

        <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
    </form>

    @if ($patterns->isEmpty())
        <x-empty-state icon="scissors" title="هنوز الگویی نساخته‌اید"
            description="یک مدل از کتابخانه انتخاب کنید و مشتری را مشخص کنید؛ الگو بی‌درنگ ساخته می‌شود.">
            <x-slot:action>
                <x-button href="{{ route('patterns.create') }}" icon="plus">ساخت اولین الگو</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($patterns as $pattern)
                <div class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
                    <a href="{{ route('patterns.show', $pattern) }}" class="block p-3">
                        @include('patterns.partials.svg', [
                            'svg' => $renderer->thumbnail($pattern),
                            'height' => 'h-44',
                        ])
                    </a>

                    <div class="flex flex-1 flex-col gap-2 border-t border-stone-100 p-4">
                        <a href="{{ route('patterns.show', $pattern) }}" class="font-bold text-stone-900 hover:text-brand-600">
                            {{ $pattern->name }}
                        </a>

                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($pattern->garmentType)
                                <x-badge color="brand" icon="shirt">{{ $pattern->garmentType->name_fa }}</x-badge>
                            @endif
                            <x-badge color="slate">سایز {{ \App\Support\Jalali::digits($pattern->base_size) }}</x-badge>
                            <x-badge color="sky">نسخه {{ \App\Support\Jalali::digits($pattern->version) }}</x-badge>
                            @if ($pattern->is_published)
                                <x-badge color="emerald" icon="share">منتشرشده</x-badge>
                            @endif
                        </div>

                        <p class="mt-auto text-xs text-stone-500">
                            {{ \App\Support\Jalali::digits($pattern->pieces->count()) }} قطعه •
                            {{ \App\Support\Format::number($pattern->totalArea() / 10000, 2) }} متر مربع پارچه
                        </p>

                        <div class="flex items-center gap-1 border-t border-stone-100 pt-2">
                            <x-button href="{{ route('patterns.editor', $pattern) }}" size="sm" variant="ghost" icon="edit">
                                ویرایشگر
                            </x-button>
                            <x-button href="{{ route('patterns.print', $pattern) }}" size="sm" variant="ghost" icon="print">
                                چاپ
                            </x-button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $patterns->links() }}</div>
    @endif
</x-app-layout>
