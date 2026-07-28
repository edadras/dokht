@php
    use App\Models\Project;
    use App\Support\Jalali;

    $statusColors = [
        'draft' => 'slate',
        'in_progress' => 'brand',
        'done' => 'emerald',
        'archived' => 'gray',
    ];
@endphp

<x-app-layout title="پروژه‌ها">
    <x-page-header title="پروژه‌ها" subtitle="هر پروژه یک لباس است که مرحله‌به‌مرحله کامل می‌شود.">
        <x-slot:actions>
            <x-button href="{{ route('projects.create') }}" icon="plus">پروژه جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- فیلتر وضعیت و جست‌وجو --}}
    <form method="GET" action="{{ route('projects.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.index', ['q' => $search]) }}"
                @class([
                    'rounded-xl border px-3 py-2 text-xs font-medium transition',
                    'border-brand-500 bg-brand-600 text-white' => $status === '',
                    'border-stone-200 bg-white text-stone-600 hover:border-brand-300' => $status !== '',
                ])>
                همه
            </a>

            @foreach (Project::STATUSES as $key => $label)
                <a href="{{ route('projects.index', ['status' => $key, 'q' => $search]) }}"
                    @class([
                        'rounded-xl border px-3 py-2 text-xs font-medium transition',
                        'border-brand-500 bg-brand-600 text-white' => $status === $key,
                        'border-stone-200 bg-white text-stone-600 hover:border-brand-300' => $status !== $key,
                    ])>
                    {{ $label }}
                    @if (($counts[$key] ?? 0) > 0)
                        <span class="opacity-70">({{ Jalali::digits($counts[$key]) }})</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="ms-auto flex items-end gap-2">
            @if ($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <x-input name="q" :value="$search" placeholder="نام پروژه یا مشتری…" class="w-56" />
            <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
        </div>
    </form>

    @if ($projects->isEmpty())
        <x-empty-state icon="shirt" title="هنوز پروژه‌ای نساخته‌اید"
            description="برای شروع فقط یک نام لازم است؛ مشتری، پارچه و الگو را در مسیر گام‌به‌گام انتخاب می‌کنید.">
            <x-slot:action>
                <x-button href="{{ route('projects.create') }}" icon="plus">شروع یک لباس تازه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                @php
                    $percent = $project->progressPercent();
                    $next = Project::STEPS[$project->nextStep()] ?? null;
                    $dash = 100.53; // محیط دایره‌ی r=16
                @endphp

                <a href="{{ route('projects.show', $project) }}"
                    class="group flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow">
                    <div class="flex items-start gap-4">
                        {{-- حلقه‌ی پیشرفت --}}
                        <span class="relative flex h-14 w-14 shrink-0 items-center justify-center">
                            <svg class="h-14 w-14 -rotate-90" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#f1eee9" stroke-width="3.5" />
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#a97449" stroke-width="3.5"
                                    stroke-linecap="round" stroke-dasharray="{{ $dash }}"
                                    stroke-dashoffset="{{ round($dash * (1 - $percent / 100), 2) }}" />
                            </svg>
                            <span class="absolute text-xs font-black text-stone-700">
                                {{ Jalali::digits($percent) }}٪
                            </span>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold text-stone-900 group-hover:text-brand-700">
                                {{ $project->name }}
                            </p>
                            <p class="mt-0.5 truncate text-sm text-stone-500">
                                {{ $project->customer?->name ?? 'بدون مشتری' }}
                                @if ($project->garmentType)
                                    • {{ $project->garmentType->name_fa }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-stone-400">{{ Jalali::ago($project->updated_at) }}</p>
                        </div>

                        <x-badge :color="$statusColors[$project->status] ?? 'slate'">
                            {{ $project->statusLabel() }}
                        </x-badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3 text-xs text-stone-500">
                        @if ($next && $percent < 100)
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 font-medium text-brand-700">
                                <x-icon name="arrow-left" class="h-3.5 w-3.5" />
                                کار بعدی: {{ $next['title'] }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">
                                <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                همه‌ی مراحل تمام شد
                            </span>
                        @endif

                        @if ($project->fabric)
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="layers" class="h-3.5 w-3.5" />
                                {{ $project->fabric->displayName() }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
</x-app-layout>
