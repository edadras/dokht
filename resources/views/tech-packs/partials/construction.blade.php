@php
    use App\Support\Jalali;

    $steps = $data['construction'] ?? [];
    $warnings = $data['construction_warnings'] ?? [];
@endphp

@if ($steps)
    <ol class="space-y-3">
        @foreach ($steps as $step)
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs font-bold text-stone-600">
                    {{ Jalali::digits((string) ($step['number'] ?? 0)) }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-stone-800">{{ $step['title'] ?? '' }}</p>
                    <p class="text-sm text-stone-600">{{ $step['description'] ?? '' }}</p>

                    @if (! empty($step['tips']))
                        <ul class="mt-1 list-inside list-disc text-xs text-stone-500">
                            @foreach ($step['tips'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@else
    <p class="text-sm text-stone-500">ترتیب دوخت ساخته نشد؛ اول الگو و نوع لباس را مشخص کنید.</p>
@endif

@if ($warnings)
    <ul class="mt-4 list-inside list-disc space-y-1 text-xs text-amber-800">
        @foreach ($warnings as $warning)
            <li>{{ $warning }}</li>
        @endforeach
    </ul>
@endif
