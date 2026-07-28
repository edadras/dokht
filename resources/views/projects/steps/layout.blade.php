@php
    use App\Support\Format;
    use App\Support\Jalali;

    // خواندن مقاوم یک درز؛ هم شکل تخت (from_piece/from_edge) و هم شکل تودرتو (from.piece)
    $side = function ($value, $edge) use ($pieces) {
        $code = is_array($value) ? $value['piece'] ?? '—' : ($value ?? '—');
        $edge = is_array($value) ? $value['edge'] ?? $edge : $edge;
        $name = $pieces->firstWhere('code', $code)?->name ?? $code;

        return $name.($edge !== null ? ' (لبه '.\App\Support\Jalali::digits((int) $edge + 1).')' : '');
    };

    $describe = fn (array $relation) => $relation['label']
        ?? $side($relation['from_piece'] ?? ($relation['from'] ?? null), $relation['from_edge'] ?? null).
            ' ← '.
            $side($relation['to_piece'] ?? ($relation['to'] ?? null), $relation['to_edge'] ?? null);
@endphp

<x-app-layout title="مرحله ۶ — چیدمان دوبعدی" wide>
    <x-page-header title="قطعه‌ها و درزها را ببینید" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="6" />

    @if (! $project->pattern)
        <x-empty-state icon="grid" title="اول الگو را بسازید"
            description="چیدمان دوبعدی از قطعه‌های الگو ساخته می‌شود.">
            <x-slot:action>
                <x-button :href="route('projects.step', [$project, 'pattern'])" icon="arrow-left">رفتن به مرحله‌ی الگو</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-5">
            {{-- ورق الگو --}}
            <x-card title="ورق الگو" icon="grid" class="lg:col-span-3"
                subtitle="همه‌ی قطعه‌ها در یک نگاه، با اندازه‌ی واقعی نسبت به هم.">
                @if ($svg)
                    <div class="thin-scroll overflow-x-auto rounded-xl border border-stone-200 bg-stone-50 p-4">
                        {!! $svg !!}
                    </div>
                @else
                    <x-empty-state icon="grid" title="قطعه‌ای برای نمایش نیست"
                        description="در ویرایشگر الگو قطعه‌ها را اضافه کنید." />
                @endif
            </x-card>

            <div class="space-y-5 lg:col-span-2">
                {{-- فهرست قطعه‌ها --}}
                <x-card title="قطعه‌ها" icon="layers" :subtitle="Format::number($pieces->count()).' قطعه'">
                    @if ($pieces->isEmpty())
                        <p class="text-sm text-stone-500">قطعه‌ای ثبت نشده است.</p>
                    @else
                        <ul class="divide-y divide-stone-100 text-sm">
                            @foreach ($pieces as $piece)
                                <li class="flex items-center gap-3 py-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-stone-100 text-xs font-bold text-stone-500">
                                        {{ Jalali::digits($loop->iteration) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-stone-800">{{ $piece->name }}</span>
                                        <span class="block text-xs text-stone-400">
                                            {{ $piece->layerLabel() }} •
                                            {{ Format::cm($piece->width()) }} × {{ Format::cm($piece->height()) }}
                                        </span>
                                    </span>
                                    <span class="text-xs text-stone-500">
                                        {{ Jalali::digits($piece->cut_quantity) }} عدد
                                        @if ($piece->on_fold)
                                            • دولا
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- دوخت مجازی --}}
                <x-card title="دوخت مجازی" icon="scissors" subtitle="کدام لبه به کدام لبه دوخته می‌شود.">
                    @if ($relations === [])
                        <p class="text-sm text-stone-500">هنوز درزی ثبت نشده است.</p>
                    @else
                        <ul class="divide-y divide-stone-100 text-sm">
                            @foreach ($relations as $index => $relation)
                                <li class="flex items-center gap-2 py-2">
                                    <span class="min-w-0 flex-1 truncate text-stone-700">{{ $describe($relation) }}</span>

                                    <form method="POST"
                                        action="{{ route('projects.step.update', [$project, 'layout']) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="remove" value="{{ $index }}">
                                        <button type="submit" class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50"
                                            aria-label="حذف درز">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($suggested !== [])
                        <form method="POST" action="{{ route('projects.step.update', [$project, 'layout']) }}"
                            class="mt-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="accept_suggestions" value="1">
                            <x-button type="submit" variant="soft" size="sm" icon="sparkles" class="w-full">
                                افزودن {{ Jalali::digits(count($suggested)) }} درز پیشنهادی سامانه
                            </x-button>
                        </form>
                    @endif

                    @if ($edgeOptions !== [])
                        <form method="POST" action="{{ route('projects.step.update', [$project, 'layout']) }}"
                            class="mt-4 space-y-3 border-t border-stone-100 pt-4">
                            @csrf
                            @method('PUT')

                            <x-field label="این لبه" name="add_from">
                                <x-select name="add_from" placeholder="انتخاب لبه" :options="$edgeOptions" />
                            </x-field>

                            <x-field label="به این لبه دوخته شود" name="add_to">
                                <x-select name="add_to" placeholder="انتخاب لبه" :options="$edgeOptions" />
                            </x-field>

                            <x-button type="submit" variant="secondary" size="sm" icon="plus" class="w-full">
                                افزودن درز
                            </x-button>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>

        <x-card class="mt-5">
            <form method="POST" action="{{ route('projects.step.update', [$project, 'layout']) }}">
                @csrf
                @method('PUT')
                <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>
            </form>

            <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                <b>سامانه چه کاری کرد؟</b>
                قطعه‌ها را با اندازه‌ی نسبی درست کنار هم گذاشت و حدس زد کدام لبه‌ها به هم دوخته می‌شوند.
                همین درزها هستند که در نمای سه‌بعدی لباس را روی مانکن می‌بندند، پس اگر درزی جا افتاده
                از فهرست کنار اضافه‌اش کنید.
            </p>
        </x-card>
    @endif
</x-app-layout>
