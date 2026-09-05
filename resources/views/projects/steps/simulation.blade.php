@php
    use App\Support\Jalali;
@endphp

<x-app-layout title="مرحله ۷ — نمای سه‌بعدی" wide>
    <x-page-header title="لباس روی مانکن چطور می‌افتد؟" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="7" />

    @if (! $payload)
        <x-empty-state icon="cube" title="برای نمای سه‌بعدی به الگو نیاز داریم"
            description="اول الگو را بسازید؛ بعد لباس با مشخصات پارچه‌ی انتخابی روی مانکن می‌افتد.">
            <x-slot:action>
                <x-button :href="route('projects.step', [$project, 'pattern'])" icon="arrow-left">رفتن به مرحله‌ی الگو</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div x-data="{ pose: @js($pose) }" class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @include('partials.server-render', [
                    'serverRender' => $serverRender,
                    'serverRenderStatusUrl' => $serverRenderStatusUrl,
                    'serverRenderTitle' => 'لباس دوخته‌شده روی مانکن',
                ])
            </div>

            <div class="space-y-5">
                {{-- حالت بدن --}}
                <x-card title="بدن در چه حالتی باشد؟" icon="user"
                    subtitle="هر حالت نیاز نواحی مختلف را تغییر می‌دهد.">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($poses as $key => $label)
                            <button type="button" @click="pose = @js($key)"
                                class="rounded-xl border-2 px-3 py-2 text-sm font-medium transition"
                                x-bind:class="pose === @js($key)
                                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                                    : 'border-stone-200 bg-white text-stone-600 hover:border-brand-300'">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('projects.simulate', $project) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="pose" x-bind:value="pose">
                        <x-button type="submit" size="lg" icon="sparkles" class="w-full">شبیه‌سازی کن</x-button>
                    </form>

                    <p class="mt-3 text-xs leading-5 text-stone-500">
                        با زدن این دکمه نتیجه ذخیره می‌شود و مستقیم به گزارش تست تناسب می‌روید.
                    </p>
                </x-card>

                {{-- خلاصه‌ی پارچه --}}
                <x-card title="پارچه‌ی این لباس" icon="layers">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">پارچه</dt>
                            <dd class="font-medium">{{ $payload['fabric']['name'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">افتادگی</dt>
                            <dd class="font-medium">{{ $payload['fabric']['drape_label'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">فرم لباس</dt>
                            <dd class="font-medium">
                                {{ [
                                    'fitted' => 'تنگ و بدن‌نما',
                                    'a_line' => 'خط A',
                                    'straight' => 'راسته',
                                    'flared' => 'کلوش',
                                ][$payload['garment']['silhouette']] ?? $payload['garment']['silhouette'] }}
                            </dd>
                        </div>
                    </dl>
                </x-card>

                @if ($simulation)
                    <x-card title="آخرین نتیجه" icon="clock">
                        <p class="text-sm text-stone-600">
                            حالت {{ $simulation->poseLabel() }} —
                            امتیاز {{ Jalali::digits((string) $simulation->fit_score) }} از ۱۰۰
                        </p>
                        <x-button :href="route('simulations.show', $simulation)" variant="secondary" size="sm"
                            icon="eye" class="mt-3 w-full">گزارش کامل</x-button>
                    </x-card>
                @endif

                <form method="POST" action="{{ route('projects.step.update', [$project, 'simulation']) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="pose" x-bind:value="pose">
                    <x-button type="submit" variant="secondary" size="lg" icon="arrow-left" class="w-full">
                        ادامه
                    </x-button>
                </form>
            </div>
        </div>

        <x-advanced-section title="پارامترهای فیزیکی پارچه" class="mt-5"
            description="عددهایی که موتور شبیه‌سازی از شناسنامه‌ی پارچه می‌سازد.">
            <div class="grid gap-3 text-xs sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($payload['fabric']['physics'] as $key => $value)
                    <div class="flex items-center justify-between rounded-xl bg-stone-50 px-3 py-2">
                        <span class="text-stone-500">{{ $key }}</span>
                        <span class="font-semibold text-stone-800">{{ Jalali::digits((string) $value) }}</span>
                    </div>
                @endforeach
            </div>
        </x-advanced-section>
    @endif
</x-app-layout>
