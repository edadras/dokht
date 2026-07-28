@php
    use App\Support\Format;

    $verdictColors = ['suitable' => 'emerald', 'acceptable' => 'amber', 'unsuitable' => 'rose'];

    $severityTypes = ['warning' => 'warning', 'suggestion' => 'tip', 'info' => 'info'];

    // فقط قواعدی که به پارچه و برش مربوط‌اند در این مرحله به کار می‌آیند
    $findings = collect($ruleFindings)
        ->filter(fn ($f) => in_array($f['scope'] ?? '', ['fabric', 'cutting'], true))
        ->take(4);
@endphp

<x-app-layout title="مرحله ۳ — انتخاب پارچه" wide>
    <x-page-header title="این لباس با چه پارچه‌ای دوخته می‌شود؟" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="3" />

    @foreach ($findings as $finding)
        <x-alert :type="$severityTypes[$finding['severity'] ?? 'info'] ?? 'info'" :title="$finding['name'] ?? null"
            class="mb-4">
            {{ $finding['message'] ?? '' }}
        </x-alert>
    @endforeach

    <form method="POST" action="{{ route('projects.step.update', [$project, 'fabric']) }}" class="space-y-5">
        @csrf
        @method('PUT')

        @if ($fabrics->isEmpty())
            <x-empty-state icon="layers" title="هنوز پارچه‌ای در انبار کارگاه نیست"
                description="پارچه‌ها را یک بار ثبت کنید تا در همه‌ی پروژه‌ها قابل انتخاب باشند.">
                <x-slot:action>
                    <x-button :href="route('fabrics.create')" icon="plus">ثبت پارچه</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($fabrics as $fabric)
                    @php
                        $profile = $fabric->profile();
                        $score = $scores[$fabric->id] ?? null;
                        $criteria = is_array($score['criteria'] ?? null) ? $score['criteria'] : [];
                    @endphp

                    <div>
                        <input type="radio" name="fabric_id" id="fabric-{{ $fabric->id }}" value="{{ $fabric->id }}"
                            class="peer sr-only" @checked((int) old('fabric_id', $project->fabric_id) === $fabric->id)>

                        <label for="fabric-{{ $fabric->id }}"
                            class="flex h-full cursor-pointer flex-col gap-3 rounded-2xl border-2 border-stone-200 bg-white p-4 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50/40">
                            <div class="flex items-center gap-3">
                                <span class="h-10 w-10 shrink-0 rounded-xl border border-stone-200"
                                    style="background: {{ $fabric->color_hex ?: '#e7e5e4' }}"></span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-stone-900">{{ $fabric->name }}</p>
                                    <p class="truncate text-xs text-stone-500">
                                        {{ $fabric->typeName() }} • {{ $fabric->color ?: 'بی‌رنگ' }}
                                    </p>
                                </div>

                                @if (is_numeric($score['overall'] ?? null))
                                    <x-badge :color="$verdictColors[$score['verdict'] ?? ''] ?? 'slate'">
                                        {{ $score['verdict_label'] ?? '' }}
                                        {{ Format::number($score['overall']) }}
                                    </x-badge>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-1.5 text-[11px] text-stone-500">
                                <span class="rounded-full bg-stone-100 px-2 py-0.5">{{ $profile->weightLabel() }}</span>
                                <span class="rounded-full bg-stone-100 px-2 py-0.5">{{ $profile->drapeLabel() }}</span>
                                @if ($profile->isStretchy())
                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">
                                        کشسانی {{ Format::percent($profile->maxStretch()) }}
                                    </span>
                                @endif
                                @if ($profile->isSheer())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">نیمه‌شفاف</span>
                                @endif
                            </div>

                            @if ($criteria !== [])
                                <div class="space-y-2 border-t border-stone-100 pt-3">
                                    @foreach (array_slice($criteria, 0, 4, true) as $key => $criterion)
                                        @php
                                            $value = is_array($criterion)
                                                ? $criterion['score'] ?? ($criterion['value'] ?? 0)
                                                : $criterion;
                                            $label = is_array($criterion) ? $criterion['label'] ?? $key : $key;
                                        @endphp

                                        <x-meter :label="(string) $label" :value="is_numeric($value) ? (float) $value : 0" />
                                    @endforeach
                                </div>
                            @endif

                            @if (! empty($score['notes']))
                                <p class="text-[11px] leading-5 text-stone-500">
                                    {{ is_array($score['notes']) ? implode(' • ', array_map('strval', $score['notes'])) : $score['notes'] }}
                                </p>
                            @endif

                            <div class="mt-auto flex items-center justify-between border-t border-stone-100 pt-3 text-xs text-stone-500">
                                <span>عرض {{ Format::cm($fabric->effectiveWidth()) }}</span>
                                <span>{{ Format::number($fabric->stock_meters, 1) }} متر در انبار</span>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            @error('fabric_id')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        @endif

        <x-card>
            <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>

            <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                <b>سامانه چه کاری کرد؟</b>
                برای هر پارچه، سنگینی، لختی، کشسانی و شفافیت را با نیاز این مدل لباس مقایسه کرد.
                همین مشخصات بعداً در نمای سه‌بعدی (افتادگی پارچه) و در تست تناسب (تحمل تنگی) به کار می‌رود.
            </p>
        </x-card>

        <x-advanced-section title="آستر و پارچه‌ی دوم" description="برای پارچه‌های نازک یا لباس‌های مجلسی.">
            <x-field label="پارچه‌ی آستر" name="lining_fabric_id">
                <x-select name="lining_fabric_id" placeholder="بدون آستر" :selected="$project->lining_fabric_id"
                    :options="$fabrics->mapWithKeys(fn ($f) => [$f->id => $f->displayName()])->all()" />
            </x-field>
        </x-advanced-section>
    </form>
</x-app-layout>
