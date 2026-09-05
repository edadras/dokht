@php
    use App\Models\Simulation;
    use App\Support\Format;
    use App\Support\Jalali;

    $zones = $simulation->zones ?? [];
@endphp

<x-app-layout title="گزارش تست تناسب" wide>
    <x-page-header title="گزارش تست تناسب"
        :subtitle="($project?->name ?? 'بدون پروژه').' — حالت '.$simulation->poseLabel()"
        :back="$project ? route('projects.step', [$project, 'fit']) : route('projects.index')">
        <x-slot:actions>
            <x-badge color="brand" icon="star">
                امتیاز {{ Jalali::digits((string) $simulation->fit_score) }} از ۱۰۰
            </x-badge>
            @if ($project)
                <x-button :href="route('projects.step', [$project, 'simulation'])" variant="secondary" icon="refresh">
                    شبیه‌سازی دوباره
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- نمای سه‌بعدی --}}
        <div class="lg:col-span-2">
            @include('partials.server-render', [
                'serverRender' => $serverRender,
                'serverRenderStatusUrl' => route('simulations.render-status', $simulation),
                'serverRenderTitle' => 'خروجی واقعی این شبیه‌سازی',
            ])

            {{-- جدول نواحی --}}
            <x-card title="نواحی بدن" icon="ruler" padding="p-0" class="mt-5">
                <div class="thin-scroll overflow-x-auto">
                    <table class="w-full min-w-max text-sm">
                        <thead class="bg-stone-50 text-xs text-stone-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium">ناحیه</th>
                                <th class="px-4 py-3 text-start font-medium">لباس</th>
                                <th class="px-4 py-3 text-start font-medium">بدن</th>
                                <th class="px-4 py-3 text-start font-medium">آزادی</th>
                                <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($zones as $zone)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-stone-800">
                                        {{ $zone['label'] ?? $zone['key'] ?? '' }}
                                    </td>
                                    <td class="px-4 py-3 text-stone-600">{{ Format::cm($zone['garment_cm'] ?? null) }}</td>
                                    <td class="px-4 py-3 text-stone-600">{{ Format::cm($zone['body_cm'] ?? null) }}</td>
                                    <td class="px-4 py-3 font-semibold" style="color: {{ $zone['color'] ?? '#57534e' }}">
                                        {{ ($zone['ease_cm'] ?? 0) > 0 ? '+' : '' }}{{ Jalali::digits((string) ($zone['ease_cm'] ?? 0)) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                            style="background: {{ $zone['color'] ?? '#6b7280' }}">
                                            {{ Simulation::levelLabel($zone['level'] ?? 'good') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-stone-500">
                                        نتیجه‌ای ثبت نشده است.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-5">
            <x-card title="مشخصات این شبیه‌سازی" icon="clipboard">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">حالت بدن</dt>
                        <dd class="font-medium">{{ $simulation->poseLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">پارچه</dt>
                        <dd class="font-medium">{{ $simulation->fabric?->displayName() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">الگو</dt>
                        <dd class="font-medium">{{ $simulation->pattern?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">اندازه‌ها</dt>
                        <dd class="font-medium">{{ $simulation->measurementSet?->displayTitle() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">زمان</dt>
                        <dd class="font-medium">{{ Jalali::dateTime($simulation->created_at) }}</dd>
                    </div>
                </dl>
            </x-card>

            @if ($simulation->hasProblems())
                <x-card title="هشدارها" icon="alert">
                    <div class="space-y-3">
                        @foreach ($simulation->warnings as $warning)
                            <x-alert type="warning">{{ $warning }}</x-alert>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if (! empty($simulation->suggestions))
                <x-card title="پیشنهادها" icon="sparkles">
                    <ul class="space-y-2 text-sm text-stone-700">
                        @foreach ($simulation->suggestions as $suggestion)
                            <li class="flex gap-2">
                                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                                <span>{{ is_array($suggestion) ? $suggestion['text'] ?? '' : $suggestion }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="راهنمای رنگ" icon="palette">
                <ul class="space-y-2 text-sm">
                    @foreach ($levels as $level)
                        <li class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background: {{ $level['color'] }}"></span>
                            {{ $level['label'] }}
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>

