{{-- یک کارت سبک؛ اگر روی پایه فعلی ننشیند خاموش است و دلیلش زیرش نوشته می‌شود. --}}
@php $reason = $availability[$key]['reason'] ?? null; @endphp

<label class="cursor-pointer" :class="! ok('{{ $key }}') && 'cursor-not-allowed'">
<input type="checkbox" class="peer sr-only" value="{{ $key }}" :checked="hasStyle('{{ $key }}')"
    :disabled="! ok('{{ $key }}')" @click.prevent="ok('{{ $key }}') && toggleStyle('{{ $key }}')">

<span class="flex h-full flex-col rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300"
    :class="! ok('{{ $key }}') && 'border-dashed bg-stone-50 opacity-70 hover:border-stone-200'">
<span class="flex items-start gap-2">
<span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 border-stone-300 text-white transition"
    :class="hasStyle('{{ $key }}') && 'border-brand-500 bg-brand-500'">
<x-icon name="check" class="h-3.5 w-3.5" x-show="hasStyle('{{ $key }}')" x-cloak />
</span>

<span class="min-w-0 flex-1">
<span class="block text-sm font-bold text-stone-900">{{ $style['label'] }}</span>
@if (! empty($style['description']))
<span class="mt-0.5 block text-xs leading-5 text-stone-500">{{ $style['description'] }}</span>
@endif
</span>
</span>

<span class="mt-2 block text-xs font-medium leading-5 text-amber-700" x-show="! ok('{{ $key }}')"
    x-text="reason('{{ $key }}')">{{ $reason }}</span>
</span>
</label>
