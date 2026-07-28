{{-- قاب نمایش SVG الگو؛ متغیرها: $svg، $height، $label --}}
@php
    $frameHeight = $height ?? 'h-64';
    $frameLabel = $label ?? null;
@endphp

<div class="relative overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
    <div class="flex {{ $frameHeight }} items-center justify-center p-2 [&>svg]:h-full [&>svg]:w-auto [&>svg]:max-w-full">
        {!! $svg !!}
    </div>

    @if ($frameLabel)
        <span class="absolute bottom-2 start-2 rounded-lg bg-white/90 px-2 py-1 text-[11px] font-medium text-stone-600">
            {{ $frameLabel }}
        </span>
    @endif
</div>
