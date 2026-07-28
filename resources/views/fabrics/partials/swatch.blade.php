{{-- نمونه رنگ پارچه؛ اگر تصویر بافت داشته باشد همان نشان داده می‌شود --}}
@php($swatchSize = $size ?? 'h-16 w-16')

<span class="{{ $swatchSize }} block shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100"
    @if ($fabric->color_hex) style="background-color: {{ $fabric->color_hex }}" @endif>
    @if ($fabric->texture_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fabric->texture_path) }}"
            alt="{{ $fabric->name }}" class="h-full w-full object-cover">
    @endif
</span>
