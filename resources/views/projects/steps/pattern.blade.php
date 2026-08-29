@php
    use App\Support\Format;

    $options = $templates->isNotEmpty() ? $templates : $allTemplates;
@endphp

<x-app-layout title="مرحله ۴ — الگو" wide>
    <x-page-header title="الگوی این لباس را بسازیم" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="4" />

    @if ($project->pattern)
        {{-- الگو ساخته شده: پیش‌نما و کارهای بعدی --}}
        <div class="grid gap-5 lg:grid-cols-3">
            <x-card title="پیش‌نمای الگو" icon="scissors" class="lg:col-span-2"
                :subtitle="$project->pattern->name.' — '.Format::number($project->pattern->pieceCount()).' قطعه'">
                @if ($svg)
                    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-stone-50 p-4">
                        {!! $svg !!}
                    </div>
                @else
                    <x-empty-state icon="scissors" title="این الگو هنوز قطعه‌ای ندارد"
                        description="در ویرایشگر الگو می‌توانید قطعه‌ها را اضافه کنید." />
                @endif
            </x-card>

            <div class="space-y-5">
                <x-card title="کارهای الگو" icon="settings">
                    <div class="space-y-2">
                        <x-button :href="route('patterns.editor', $project->pattern)" variant="secondary" icon="edit"
                            class="w-full">ویرایشگر الگو</x-button>
                        <x-button :href="route('patterns.show', $project->pattern)" variant="secondary" icon="eye"
                            class="w-full">جزئیات الگو</x-button>
                        <x-button :href="route('patterns.print', $project->pattern)" variant="secondary" icon="print"
                            class="w-full">چاپ در اندازه‌ی واقعی</x-button>
                    </div>
                </x-card>

                <x-card title="بازتولید الگو" icon="refresh"
                    subtitle="اگر اندازه‌ها یا مدل عوض شده، الگو را از نو بسازید.">
                    <form method="POST" action="{{ route('projects.pattern.generate', $project) }}" class="space-y-3">
                        @csrf

                        <x-field label="الگوی پایه" name="pattern_template_id">
                            <x-select name="pattern_template_id" placeholder="انتخاب الگوی پایه"
                                :selected="$project->pattern->pattern_template_id"
                                :options="$options->pluck('name_fa', 'id')->all()" />
                        </x-field>

                        <x-button type="submit" variant="soft" icon="refresh" class="w-full">بازتولید</x-button>
                    </form>
                </x-card>

                <form method="POST" action="{{ route('projects.step.update', [$project, 'pattern']) }}">
                    @csrf
                    @method('PUT')
                    <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>
                </form>
            </div>
        </div>
    @elseif ($options->isEmpty())
        <x-empty-state icon="scissors" title="الگوی پایه‌ای برای این مدل پیدا نشد"
            description="مدیر سامانه باید الگوهای پایه را اضافه کند، یا می‌توانید یک الگوی آماده از کتابخانه کپی کنید.">
            <x-slot:action>
                <x-button :href="route('library.index')" icon="grid">کتابخانه مدل‌ها</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        {{-- انتخاب الگوی پایه --}}
        <form method="POST" action="{{ route('projects.pattern.generate', $project) }}" class="space-y-5">
            @csrf

            <x-card title="کدام الگوی پایه؟" icon="scissors"
                subtitle="الگو با اندازه‌های همین پروژه و آزادی مدل ساخته می‌شود.">
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($options as $template)
                        <div>
                            <input type="radio" name="pattern_template_id" id="template-{{ $template->id }}"
                                value="{{ $template->id }}" class="peer sr-only"
                                @checked((int) old('pattern_template_id') === $template->id || $loop->first)>

                            <label for="template-{{ $template->id }}"
                                class="flex h-full cursor-pointer flex-col gap-2 rounded-2xl border-2 border-stone-200 bg-white p-3 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
                                <div class="flex h-28 items-center justify-center overflow-hidden rounded-xl bg-stone-50">
                                    <img loading="lazy" decoding="async" alt="{{ $template->name_fa }}"
                                        src="{{ route('patterns.templates.preview', $template) }}"
                                        class="h-full w-auto object-contain">
                                </div>

                                <p class="text-sm font-semibold text-stone-800">{{ $template->name_fa }}</p>

                                @if ($template->description)
                                    <p class="text-[11px] leading-5 text-stone-500">{{ $template->description }}</p>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>

                @error('pattern_template_id')
                    <p class="mt-4 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </x-card>

            <x-card>
                <x-button type="submit" size="lg" icon="sparkles" class="w-full">الگو را بساز</x-button>

                <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                    <b>سامانه چه کاری می‌کند؟</b>
                    قطعه‌های الگو را با اندازه‌های
                    {{ $project->measurementSet?->displayTitle() ?? 'سایز '.($project->size ?: '۴۰') }}
                    و آزادی مدل رسم می‌کند، راستای پارچه و خط‌های نشانه را می‌گذارد و درزهای متناظر را
                    خودش حدس می‌زند تا در مرحله‌ی بعد فقط تأیید کنید.
                </p>
            </x-card>
        </form>
    @endif
</x-app-layout>
