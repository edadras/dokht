@props(['name' => null, 'type' => 'text', 'value' => null])

@php
    $hasError = $name && $errors->has($name);
@endphp

<input type="{{ $type }}" @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    value="{{ $name ? old($name, $value) : $value }}"
    {{ $attributes->merge([
        'class' =>
            'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-stone-400 focus:ring-2 focus:ring-brand-200 '.
            ($hasError ? 'border-rose-400 focus:border-rose-500' : 'border-stone-300 focus:border-brand-500'),
    ]) }}>
