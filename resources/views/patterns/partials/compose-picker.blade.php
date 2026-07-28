{{-- یک انتخابگر تصویری برای یک بخش از لباس (بالاتنه، آستین، پایین‌تنه، یقه) --}}
<x-card :title="$title" :icon="$icon" :subtitle="$subtitle ?? null">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($items as $key => $item)
            <label class="cursor-pointer">
                <input type="radio" name="{{ $group }}" value="{{ $key }}" class="peer sr-only"
                    x-model="selection.{{ $group }}" @checked((string) old($group, $selected) === (string) $key)>

                <div class="flex h-full flex-col rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300">
                    <div class="flex h-24 items-center justify-center overflow-hidden rounded-xl bg-stone-50 [&>svg]:h-full [&>svg]:w-auto">
                        @if (! empty($item['thumbnail']))
                            {!! $item['thumbnail'] !!}
                        @else
                            <span class="flex flex-col items-center gap-1 text-stone-400">
                                <x-icon name="x" class="h-6 w-6" />
                                <span class="text-xs">بدون این قطعه</span>
                            </span>
                        @endif
                    </div>

                    <p class="mt-2.5 text-sm font-bold text-stone-900">{{ $item['label'] }}</p>

                    @if (! empty($item['hint']))
                        <p class="mt-0.5 text-xs leading-5 text-stone-500">{{ $item['hint'] }}</p>
                    @endif
                </div>
            </label>
        @endforeach
    </div>

    @error($group)
        <p class="mt-3 text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</x-card>
