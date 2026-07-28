{{-- فهرست نتیجه‌های موتور قواعد؛ هر پیام با شدت خودش نشان داده می‌شود --}}
@php
    $ruleResults = $results ?? [];
    $ruleEmpty = $empty ?? 'نکته خاصی برای این بخش نیست.';
@endphp

@if (empty($ruleResults))
    <p class="text-sm text-stone-500">{{ $ruleEmpty }}</p>
@else
    <ul class="space-y-3">
        @foreach ($ruleResults as $result)
            @php
                $tone = match ($result['severity']) {
                    'warning' => ['badge' => 'rose', 'icon' => 'alert'],
                    'info' => ['badge' => 'sky', 'icon' => 'info'],
                    default => ['badge' => 'brand', 'icon' => 'sparkles'],
                };
            @endphp

            <li class="flex gap-3">
                <span class="mt-0.5 shrink-0 text-stone-400">
                    <x-icon :name="$tone['icon']" class="h-5 w-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-stone-800">{{ $result['name'] }}</p>
                        <x-badge :color="$tone['badge']">
                            {{ \App\Models\DesignRule::SEVERITIES[$result['severity']] ?? $result['severity'] }}
                        </x-badge>
                    </div>

                    <ul class="mt-1 space-y-1">
                        @foreach ($result['actions'] as $action)
                            <li class="text-sm text-stone-600">— {{ $action['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </li>
        @endforeach
    </ul>
@endif
