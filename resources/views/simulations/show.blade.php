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
            @if ($payload)
                <div x-data="garmentViewer(@js(['payload' => $payload, 'pose' => $simulation->pose]))">
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-stone-900 shadow-sm">
                    <div x-ref="stage" class="relative aspect-4/5 w-full sm:aspect-video">
                        <div x-cloak x-show="! supported"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-stone-200">
                            <x-icon name="alert" class="h-8 w-8" />
                            <p class="text-sm leading-6">
                                مرورگر شما نمای سه‌بعدی را پشتیبانی نمی‌کند؛ جدول پایین همه‌ی نتیجه را نشان می‌دهد.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-stone-800 px-4 py-3">
                        <button type="button" @click="setView('front')"
                            class="rounded-lg bg-stone-800 px-3 py-1.5 text-xs text-stone-200 hover:bg-stone-700">جلو</button>
                        <button type="button" @click="setView('side')"
                            class="rounded-lg bg-stone-800 px-3 py-1.5 text-xs text-stone-200 hover:bg-stone-700">پهلو</button>
                        <button type="button" @click="setView('back')"
                            class="rounded-lg bg-stone-800 px-3 py-1.5 text-xs text-stone-200 hover:bg-stone-700">پشت</button>
                        <button type="button" @click="toggleRotate()" class="rounded-lg px-3 py-1.5 text-xs"
                            x-bind:class="autoRotate ? 'bg-brand-600 text-white' : 'bg-stone-800 text-stone-200'">
                            چرخش خودکار
                        </button>
                        <button type="button" @click="toggleZones()" class="rounded-lg px-3 py-1.5 text-xs"
                            x-bind:class="showZones ? 'bg-brand-600 text-white' : 'bg-stone-800 text-stone-200'">
                            نقشه‌ی فشار
                        </button>
                        <button type="button" @click="toggleLive()" class="rounded-lg px-3 py-1.5 text-xs"
                            x-bind:class="liveSim ? 'bg-brand-600 text-white' : 'bg-stone-800 text-stone-200'">
                            شبیه‌سازی زنده
                        </button>
                        <button type="button" x-show="canDrape" @click="toggleTrueSeams()"
                            class="rounded-lg px-3 py-1.5 text-xs"
                            x-bind:class="trueSeams ? 'bg-brand-600 text-white' : 'bg-stone-800 text-stone-200'">
                            دوخت قطعه‌های الگو
                        </button>
                    </div>
                </div>

                {{-- توضیح یک‌خطی: کاربر باید بداند چه چیزی را دارد می‌بیند --}}
                <p class="mt-3 text-xs leading-6 text-stone-500">
                    <span x-show="trueSeams && canDrape">
                        آنچه می‌بینید دوختِ خودِ الگوست: هر قطعه از روی مسیر واقعی‌اش بریده و مثلث‌بندی شده،
                        درزها و ساسون‌ها به هم دوخته شده‌اند و پارچه زیر وزن خودش روی همین بدن نشسته است.
                    </span>
                    <span x-show="! trueSeams || ! canDrape">
                        پارچه زنده حساب می‌شود: زیر وزن خودش می‌افتد و به بدن برخورد می‌کند، پس لباس هیچ‌جا داخل
                        مانکن فرو نمی‌رود و افتادگی‌اش از مشخصات همین پارچه می‌آید.
                    </span>
                </p>
                </div>
            @else
                <x-empty-state icon="cube" title="نمای سه‌بعدی برای این گزارش موجود نیست"
                    description="الگوی این شبیه‌سازی حذف شده است." />
            @endif

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
