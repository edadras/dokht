@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'suffix' => null])

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="block text-sm font-medium text-stone-700">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        {{ $slot }}

        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-stone-400">
                {{ $suffix }}
            </span>
        @endif
    </div>

    @if ($hint)
        <p class="text-xs text-stone-500">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
        @enderror
    @endif
</div>
