<x-app-layout title="کتابخانه مدل‌ها" wide>
    <x-page-header title="کتابخانه مدل‌ها"
        subtitle="از یک الگوی پایه شروع کنید، یا الگوی آماده کارگاه‌های دیگر را بردارید." />

    {{-- دو بخش کتابخانه --}}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @foreach ([
            'templates' => ['label' => 'الگوهای پایه', 'icon' => 'ruler', 'count' => $templates->count()],
            'published' => ['label' => 'الگوهای منتشرشده', 'icon' => 'share', 'count' => $published->total()],
        ] as $key => $meta)
            <a href="{{ route('library.index', array_filter(['tab' => $key, 'q' => $term, 'garment_type' => $garmentTypeId, 'category' => $category])) }}"
                @class([
                    'inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition',
                    'border-brand-500 bg-brand-600 text-white' => $tab === $key,
                    'border-stone-200 bg-white text-stone-600 hover:border-brand-300' => $tab !== $key,
                ])>
                <x-icon :name="$meta['icon']" class="h-4 w-4" />
                {{ $meta['label'] }}
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs',
                    'bg-white/20' => $tab === $key,
                    'bg-stone-100 text-stone-600' => $tab !== $key,
                ])>{{ \App\Support\Format::number($meta['count']) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('library.index') }}"
        class="mb-6 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="نام الگو…" />
        </x-field>

        <x-field label="نوع لباس" name="garment_type">
            <x-select name="garment_type" placeholder="همه" :selected="$garmentTypeId"
                :options="$garmentTypes->pluck('name_fa', 'id')->all()" />
        </x-field>

        <x-field label="دسته" name="category">
            <x-select name="category" placeholder="همه" :selected="$category"
                :options="\App\Models\GarmentType::CATEGORIES" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('library.index', ['tab' => $tab]) }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($tab === 'templates')
        @if ($templates->isEmpty())
            <x-empty-state icon="ruler" title="الگوی پایه‌ای پیدا نشد"
                description="الگوهای پایه (بلوک‌های استاندارد) نقطه شروع هر مدل تازه هستند." />
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($templates as $template)
                    <div class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                        <div class="flex h-44 items-center justify-center border-b border-stone-100 bg-stone-50 p-3">
                            @if ($template->preview_svg)
                                <div class="max-h-full [&>svg]:max-h-40 [&>svg]:w-auto">{!! $template->preview_svg !!}</div>
                            @else
                                <x-icon name="ruler" class="h-10 w-10 text-stone-300" />
                            @endif
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-2 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-stone-900">{{ $template->name_fa }}</h3>
                                @if ($template->workshop_id)
                                    <x-badge color="clay">اختصاصی کارگاه</x-badge>
                                @endif
                            </div>

                            @if ($template->garmentType)
                                <p class="text-xs text-stone-500">
                                    {{ $template->garmentType->name_fa }} •
                                    {{ $template->garmentType->categoryLabel() }}
                                </p>
                            @endif

                            @if ($template->description)
                                <p class="line-clamp-3 text-xs leading-relaxed text-stone-600">
                                    {{ $template->description }}
                                </p>
                            @endif

                            <div class="mt-auto pt-2">
                                <x-button href="{{ route('patterns.create', ['template' => $template->id]) }}"
                                    size="sm" icon="plus" class="w-full">
                                    شروع با این الگو
                                </x-button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        @if ($published->isEmpty())
            <x-empty-state icon="share" title="هنوز الگوی منتشرشده‌ای نیست"
                description="هر کارگاه می‌تواند الگوهای خود را منتشر کند تا دیگران هم استفاده کنند." />
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($published as $pattern)
                    <a href="{{ route('library.show', $pattern->id) }}"
                        class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
                        <div class="flex h-44 items-center justify-center border-b border-stone-100 bg-stone-50 p-3">
                            @if ($thumbnails[$pattern->id] ?? null)
                                <div class="max-h-full [&>svg]:max-h-40 [&>svg]:w-auto">
                                    {!! $thumbnails[$pattern->id] !!}
                                </div>
                            @else
                                <x-icon name="scissors" class="h-10 w-10 text-stone-300" />
                            @endif
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-2 p-4">
                            <h3 class="font-bold text-stone-900">{{ $pattern->name }}</h3>

                            <p class="text-xs text-stone-500">
                                {{ $pattern->garmentType?->name_fa ?? 'بدون نوع لباس' }} •
                                سایز {{ \App\Support\Jalali::digits($pattern->base_size) }}
                            </p>

                            <div class="flex flex-wrap gap-1.5">
                                <x-badge color="slate" icon="layers">
                                    {{ \App\Support\Jalali::digits($pattern->pieces->count()) }} قطعه
                                </x-badge>
                                <x-badge color="brand" icon="copy">
                                    {{ \App\Support\Jalali::digits($pattern->copies_count) }} کپی
                                </x-badge>
                            </div>

                            <p class="mt-auto pt-2 text-xs text-stone-500">
                                منتشرشده توسط {{ $pattern->workshop?->name ?? 'کارگاه ناشناس' }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $published->links() }}</div>
        @endif
    @endif
</x-app-layout>
