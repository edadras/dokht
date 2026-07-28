@props(['name' => null, 'options' => [], 'selected' => null, 'placeholder' => null])

@php
    $hasError = $name && $errors->has($name);
    $current = $name ? old($name, $selected) : $selected;
@endphp

<select @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    {{ $attributes->merge([
        'class' =>
            'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:ring-2 focus:ring-brand-200 '.
            ($hasError ? 'border-rose-400 focus:border-rose-500' : 'border-stone-300 focus:border-brand-500'),
    ]) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $optionValue => $label)
        <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $label }}</option>
    @endforeach

    {{ $slot }}
</select>
