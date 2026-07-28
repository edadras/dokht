@php
    use App\Models\Project;
    use App\Models\Simulation;
    use App\Support\Format;
    use App\Support\Jalali;

    $statusColors = ['draft' => 'slate', 'in_progress' => 'brand', 'done' => 'emerald', 'archived' => 'gray'];
    $percent = $project->progressPercent();
    $next = Project::STEPS[$project->nextStep()] ?? null;

    $cardLabels = [
        'pattern_quality' => 'کیفیت الگو',
        'fabric_compatibility' => 'سازگاری پارچه',
        'fit' => 'تناسب با بدن',
        'cutting_efficiency' => 'بازده برش',
        'production_readiness' => 'آمادگی تولید',
    ];
@endphp

<x-app-layout :title="$project->name">
    <x-page-header :title="$project->name" :back="route('projects.index')"
        :subtitle="($project->customer?->name ?? 'بدون مشتری').' • '.($project->garmentType?->name_fa ?? 'مدل انتخاب نشده')">
        <x-slot:actions>
            <x-badge :color="$statusColors[$project->status] ?? 'slate'">{{ $project->statusLabel() }}</x-badge>
            <x-button href="{{ route('projects.step', [$project, $project->nextStepKey()]) }}" icon="arrow-left">
                ادامه‌ی کار
            </x-button>
            <x-button href="{{ route('projects.edit', $project) }}" variant="secondary" icon="edit">ویرایش</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-step-nav :project="$project" :current="$project->step" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- پیشرفت کلی --}}
            <x-card title="کار تا کجا رسیده؟" icon="chart">
                <div class="space-y-4">
                    <x-meter label="پیشرفت پروژه" :value="$percent" />

                    @if ($next && $percent < 100)
                        <x-alert type="tip" title="کار بعدی شما">
                            {{ $next['title'] }} —
                            <a class="underline" href="{{ route('projects.step', [$project, $next['key']]) }}">
                                همین حالا انجامش بدهید
                            </a>
                        </x-alert>
                    @else
                        <x-alert type="success">همه‌ی مراحل این پروژه تمام شده است.</x-alert>
                    @endif

                    <ol class="divide-y divide-stone-100">
                        @foreach (Project::STEPS as $number => $step)
                            @php $done = $completed[$number] ?? false; @endphp

                            <li class="flex items-center gap-3 py-2.5">
                                <span @class([
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-emerald-500 text-white' => $done,
                                    'bg-stone-100 text-stone-500' => ! $done,
                                ])>
                                    @if ($done)
                                        <x-icon name="check" class="h-4 w-4" />
                                    @else
                                        {{ Jalali::digits($number) }}
                                    @endif
                                </span>

                                <a href="{{ route('projects.step', [$project, $step['key']]) }}"
                                    class="min-w-0 flex-1 text-sm font-medium text-stone-700 hover:text-brand-700">
                                    {{ $step['title'] }}
                                </a>

                                <span class="text-xs {{ $done ? 'text-emerald-600' : 'text-stone-400' }}">
                                    {{ $done ? 'انجام شد' : 'مانده' }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </x-card>

            {{-- آخرین شبیه‌سازی --}}
            <x-card title="آخرین تست تناسب" icon="cube">
                <x-slot:actions>
                    @if ($project->latestSimulation)
                        <x-button :href="route('simulations.show', $project->latestSimulation)" variant="secondary"
                            size="sm" icon="eye">گزارش کامل</x-button>
                    @endif
                </x-slot:actions>

                @if ($simulation = $project->latestSimulation)
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-badge color="brand" icon="cube">{{ $simulation->poseLabel() }}</x-badge>
                            <span class="text-sm text-stone-600">
                                امتیاز تناسب: <b>{{ Jalali::digits((string) $simulation->fit_score) }}</b> از ۱۰۰
                            </span>
                            <span class="text-xs text-stone-400">{{ Jalali::ago($simulation->created_at) }}</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach ($simulation->zones ?? [] as $zone)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 px-2.5 py-1 text-xs">
                                    <span class="h-2.5 w-2.5 rounded-full"
                                        style="background: {{ $zone['color'] ?? '#6b7280' }}"></span>
                                    {{ $zone['label'] ?? $zone['key'] ?? '' }}
                                    <span class="text-stone-400">
                                        {{ Simulation::levelLabel($zone['level'] ?? 'good') }}
                                    </span>
                                </span>
                            @endforeach
                        </div>

                        @foreach (array_slice($simulation->warnings ?? [], 0, 3) as $warning)
                            <x-alert type="warning">{{ $warning }}</x-alert>
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="cube" title="هنوز لباس را روی مانکن ندیده‌اید"
                        description="با یک کلیک لباس روی مانکن سه‌بعدی می‌افتد و نقاط تنگ و گشاد رنگی می‌شود.">
                        <x-slot:action>
                            <x-button :href="route('projects.step', [$project, 'simulation'])" icon="cube">
                                رفتن به نمای سه‌بعدی
                            </x-button>
                        </x-slot:action>
                    </x-empty-state>
                @endif
            </x-card>

            {{-- برش و کارت فنی --}}
            <div class="grid gap-5 sm:grid-cols-2">
                <x-card title="برش پارچه" icon="scissors">
                    @if ($layout = $project->latestCuttingLayout)
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-stone-500">طول لازم</dt>
                                <dd class="font-semibold">{{ Format::number($layout->requiredMeters(), 2) }} متر</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">بازده چیدمان</dt>
                                <dd class="font-semibold">{{ Format::percent($layout->efficiency) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">دورریز</dt>
                                <dd class="font-semibold">{{ Format::percent($layout->waste_percent) }}</dd>
                            </div>
                        </dl>

                        <x-button :href="route('cutting-layouts.show', $layout)" variant="secondary" size="sm"
                            icon="grid" class="mt-4 w-full">دیدن نقشه‌ی برش</x-button>
                    @else
                        <p class="text-sm text-stone-500">هنوز چیدمان برش ساخته نشده است.</p>
                        <x-button :href="route('projects.step', [$project, 'cutting'])" variant="secondary" size="sm"
                            icon="scissors" class="mt-4 w-full">رفتن به مرحله‌ی برش</x-button>
                    @endif
                </x-card>

                <x-card title="کارت فنی" icon="file">
                    @if ($techPack = $project->techPacks->first())
                        <p class="text-sm text-stone-600">
                            نسخه‌ی {{ Jalali::digits($techPack->version) }} —
                            {{ Jalali::date($techPack->created_at) }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-button :href="route('tech-packs.show', $project)" variant="secondary" size="sm"
                                icon="file">دیدن</x-button>
                            <x-button :href="route('projects.export', $project)" variant="secondary" size="sm"
                                icon="download">خروجی</x-button>
                        </div>
                    @else
                        <p class="text-sm text-stone-500">کارت فنی ساخته نشده است.</p>
                        <x-button :href="route('projects.step', [$project, 'techpack'])" variant="secondary" size="sm"
                            icon="file" class="mt-4 w-full">رفتن به کارت فنی</x-button>
                    @endif
                </x-card>
            </div>

            @if ($project->notes)
                <x-card title="یادداشت‌ها" icon="clipboard">
                    <p class="whitespace-pre-line text-sm leading-7 text-stone-700">{{ $project->notes }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            {{-- کارنامه‌ی کیفیت --}}
            <x-card title="کارنامه‌ی کیفیت" icon="star"
                subtitle="هر عدد از ۱۰۰؛ بخش‌های انجام‌نشده صفر می‌مانند.">
                @if ($scorecard)
                    <div class="space-y-4">
                        <div class="flex items-center gap-4 rounded-xl bg-stone-50 p-4">
                            <span class="text-3xl font-black text-brand-700">
                                {{ Jalali::digits((string) ($scorecard['overall'] ?? 0)) }}
                            </span>
                            <div class="text-sm">
                                <p class="font-semibold text-stone-800">امتیاز کلی طراحی</p>
                                <p class="text-xs text-stone-500">میانگین وزنی همه‌ی بخش‌ها</p>
                            </div>
                        </div>

                        @foreach ($cardLabels as $key => $label)
                            <x-meter :label="$label" :value="$scorecard[$key] ?? 0" />
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-stone-500">برای گرفتن کارنامه، الگو و پارچه را انتخاب کنید.</p>
                @endif
            </x-card>

            {{-- خلاصه‌ی مشخصات --}}
            <x-card title="مشخصات پروژه" icon="clipboard">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">مشتری</dt>
                        <dd class="font-medium">{{ $project->customer?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">مدل</dt>
                        <dd class="font-medium">{{ $project->garmentType?->name_fa ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">سایز</dt>
                        <dd class="font-medium">{{ $project->size ? Jalali::digits($project->size) : '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">اندازه‌ها</dt>
                        <dd class="font-medium">{{ $project->measurementSet?->summary() ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">پارچه</dt>
                        <dd class="font-medium">{{ $project->fabric?->displayName() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">آستر</dt>
                        <dd class="font-medium">{{ $project->liningFabric?->displayName() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">الگو</dt>
                        <dd class="font-medium">
                            @if ($project->pattern)
                                <a class="text-brand-700 underline" href="{{ route('patterns.show', $project->pattern) }}">
                                    {{ $project->pattern->name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="کارهای دیگر" icon="settings">
                <div class="space-y-2">
                    @if ($project->pattern)
                        <x-button :href="route('patterns.editor', $project->pattern)" variant="secondary" size="sm"
                            icon="edit" class="w-full">ویرایشگر الگو</x-button>
                        <x-button :href="route('patterns.print', $project->pattern)" variant="secondary" size="sm"
                            icon="print" class="w-full">چاپ الگو</x-button>
                    @endif

                    <x-confirm-delete :action="route('projects.destroy', $project)" label="حذف پروژه"
                        message="این پروژه حذف شود؟ الگو و شبیه‌سازی‌های آن هم از فهرست خارج می‌شوند." />
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
