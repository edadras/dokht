@php
    use App\Support\Jalali;

    $garment = $data['garment'] ?? [];
@endphp

<x-print-layout title="کارت فنی">
    <header class="mb-6 border-b-2 border-stone-800 pb-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs text-stone-500">کارت فنی تولید</p>
                <h1 class="text-2xl font-black">{{ $garment['name'] ?? $project->name }}</h1>
                <p class="mt-1 text-sm text-stone-600">
                    {{ collect([
                        $garment['type'] ?? null,
                        isset($garment['size']) ? 'سایز '.Jalali::digits((string) $garment['size']) : null,
                        $garment['customer'] ? 'مشتری: '.$garment['customer'] : null,
                    ])->filter()->implode(' • ') }}
                </p>
            </div>

            <div class="text-end text-xs text-stone-600">
                <p class="font-bold">{{ $garment['workshop'] ?? auth()->user()->workshop?->name }}</p>
                <p>{{ $data['generated_at'] ?? Jalali::dateTime(now()) }}</p>
                @if ($techPack)
                    <p>نسخه {{ Jalali::digits((string) $techPack->version) }}</p>
                @endif
            </div>
        </div>
    </header>

    @if ($thumbnails)
        <section class="mb-6 break-inside-avoid">
            <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">نمای قطعه‌ها</h2>
            <div class="grid grid-cols-3 gap-3">
                @foreach ($thumbnails as $thumbnail)
                    <div class="rounded border border-stone-300 p-2">
                        <p class="mb-1 text-xs font-semibold">{{ $thumbnail['label'] }}</p>
                        {!! $thumbnail['svg'] !!}
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۱. خلاصه فنی</h2>
        @include('tech-packs.partials.overview', ['data' => $data])
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۲. برگه اندازه</h2>
        @include('tech-packs.partials.measurements', ['data' => $data])
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۳. قطعه‌های الگو</h2>
        @include('tech-packs.partials.pieces', ['data' => $data])
    </section>

    @if ($plan)
        <section class="mb-6 break-inside-avoid">
            <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۴. نقشه برش</h2>
            <p class="mb-2 text-sm text-stone-600">{{ $layout?->summaryText() }}</p>
            <div class="rounded border border-stone-300 p-2">{!! $plan !!}</div>
        </section>
    @endif

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۵. ترتیب دوخت</h2>
        @include('tech-packs.partials.construction', ['data' => $data])
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۶. مواد و متعلقات</h2>
        @include('tech-packs.partials.materials', ['data' => $data])
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">۷. تناسب و کیفیت</h2>
        @include('tech-packs.partials.fit', ['data' => $data])
    </section>

    @if (! empty($data['missing']))
        <section class="mb-6">
            <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">موارد ناقص</h2>
            <ul class="list-inside list-disc space-y-1 text-sm">
                @foreach ($data['missing'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (! empty($data['notes']))
        <section>
            <h2 class="mb-2 border-b border-stone-300 pb-1 font-bold">یادداشت‌ها</h2>
            <ul class="list-inside list-disc space-y-1 text-sm">
                @foreach ($data['notes'] as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</x-print-layout>
