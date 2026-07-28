@props(['project', 'current' => null])

@php
    $completed = $project->completedSteps();
    $current ??= $project->step;
@endphp

{{-- نوار مراحل پروژه؛ کاربر همیشه می‌داند کجاست و چه چیزی مانده --}}
<div class="mb-6 overflow-x-auto thin-scroll no-print">
    <ol class="flex min-w-max items-center gap-1">
        @foreach (\App\Models\Project::STEPS as $number => $step)
            @php
                $isCurrent = $number === (int) $current;
                $isDone = $completed[$number] ?? false;
            @endphp

            <li>
                <a href="{{ route('projects.step', [$project, $step['key']]) }}"
                    @class([
                        'flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium transition',
                        'border-brand-500 bg-brand-600 text-white' => $isCurrent,
                        'border-emerald-200 bg-emerald-50 text-emerald-700' => ! $isCurrent && $isDone,
                        'border-stone-200 bg-white text-stone-500 hover:border-brand-300' => ! $isCurrent && ! $isDone,
                    ])>
                    <span @class([
                        'flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold',
                        'bg-white/20' => $isCurrent,
                        'bg-emerald-500 text-white' => ! $isCurrent && $isDone,
                        'bg-stone-100' => ! $isCurrent && ! $isDone,
                    ])>
                        @if ($isDone && ! $isCurrent)
                            <x-icon name="check" class="h-3 w-3" />
                        @else
                            {{ \App\Support\Jalali::digits($number) }}
                        @endif
                    </span>
                    {{ $step['title'] }}
                </a>
            </li>
        @endforeach
    </ol>
</div>
