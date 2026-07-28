@php
    use App\Support\Jalali;
    use App\Support\Measurements;

    $defaultMode = old('mode', $sets->isNotEmpty() ? 'existing' : 'inline');
@endphp

<x-app-layout title="مرحله ۲ — اندازه بدن">
    <x-page-header title="اندازه‌های بدن را از کجا بگیریم؟" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="2" />

    <form method="POST" action="{{ route('projects.step.update', [$project, 'body']) }}" class="space-y-5"
        x-data="{ mode: @js($defaultMode) }">
        @csrf
        @method('PUT')
        <input type="hidden" name="mode" x-bind:value="mode">

        {{-- انتخاب روش --}}
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                'existing' => ['دفترچه‌ی اندازه', 'مشتری قبلاً اندازه‌گیری شده است.', 'book'],
                'inline' => ['همین حالا اندازه می‌گیرم', 'شش عدد ساده کافی است.', 'ruler'],
                'size' => ['سایز استاندارد', 'اندازه‌ی دقیق ندارم.', 'grid'],
            ] as $key => [$title, $hint, $icon])
                <button type="button" @click="mode = @js($key)"
                    class="flex items-start gap-3 rounded-2xl border-2 bg-white p-4 text-start transition"
                    x-bind:class="mode === @js($key) ? 'border-brand-500 bg-brand-50' : 'border-stone-200 hover:border-brand-300'">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-500">
                        <x-icon :name="$icon" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-stone-800">{{ $title }}</span>
                        <span class="block text-xs text-stone-500">{{ $hint }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- روش ۱: دفترچه‌ی اندازه‌ی مشتری --}}
        <div x-cloak x-show="mode === 'existing'">
            <x-card title="از دفترچه‌ی اندازه" icon="book">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="مشتری" name="customer_id" hint="تازه‌ترین مشتری‌ها بالای فهرست هستند.">
                        <x-select name="customer_id" placeholder="انتخاب مشتری"
                            :options="$customers->pluck('name', 'id')->all()" :selected="$selectedCustomerId"
                            onchange="this.form.action=@js(route('projects.step', [$project, 'body'])); this.form.method='get'; this.form.submit();" />
                    </x-field>

                    <x-field label="کدام مجموعه اندازه؟" name="measurement_set_id">
                        @if ($sets->isEmpty())
                            <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                                این مشتری دفترچه‌ی اندازه ندارد؛ از روش «همین حالا اندازه می‌گیرم» استفاده کنید.
                            </p>
                        @else
                            <x-select name="measurement_set_id" :selected="$project->measurement_set_id"
                                :options="$sets->mapWithKeys(fn ($set) => [
                                    $set->id => $set->displayTitle().' — '.($set->summary() ?: 'بدون خلاصه'),
                                ])->all()" />
                        @endif
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- روش ۲: شش عدد ضروری --}}
        <div x-cloak x-show="mode === 'inline'">
            <x-card title="شش عدد کافی است" icon="ruler"
                subtitle="بقیه‌ی اندازه‌ها (گردن، بازو، حلقه آستین و…) خودکار تخمین زده می‌شود.">
                <div class="space-y-5">
                    <x-field label="نام مشتری" name="customer_name"
                        hint="اگر نام بنویسید، مشتری و دفترچه‌ی اندازه‌اش خودکار ساخته می‌شود.">
                        <x-input name="customer_name" :value="$project->customer?->name"
                            placeholder="مثلاً زهرا کریمی" />
                    </x-field>

                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach ($essential as $key => $field)
                            <x-field :label="$field['label']" :name="$key" suffix="س‌م" required>
                                <x-input type="number" step="0.5" :name="$key"
                                    :value="$measurements[$key] ?? $field['default'] ?? null" />
                            </x-field>
                        @endforeach
                    </div>

                    <x-alert type="tip">
                        نزدیک‌ترین سایز استاندارد به این اعداد: <b>{{ Jalali::digits($guessedSize) }}</b>.
                        اگر عددی را نمی‌دانید همان مقدار پیشنهادی را رها کنید.
                    </x-alert>
                </div>
            </x-card>
        </div>

        {{-- روش ۳: سایز استاندارد --}}
        <div x-cloak x-show="mode === 'size'">
            <x-card title="سایز استاندارد" icon="grid" subtitle="جدول سایز بانوان؛ همه‌ی اندازه‌ها از همین جدول پر می‌شود.">
                <div class="grid gap-3 sm:grid-cols-4">
                    @foreach (Measurements::SIZE_TABLE as $size => $row)
                        <label class="cursor-pointer">
                            <input type="radio" name="size" value="{{ $size }}" class="peer sr-only"
                                @checked((string) old('size', $project->size) === (string) $size)>
                            <span
                                class="flex flex-col items-center gap-1 rounded-2xl border-2 border-stone-200 bg-white p-3 text-center transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
                                <span class="text-lg font-black text-stone-800">{{ Jalali::digits($size) }}</span>
                                <span class="text-[11px] text-stone-500">
                                    سینه {{ Jalali::digits($row['bust']) }} • کمر {{ Jalali::digits($row['waist']) }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>
        </div>

        <x-card>
            <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>

            <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                <b>سامانه چه کاری کرد؟</b>
                از روی همین چند عدد، بیست و چند اندازه‌ی دیگر با نسبت‌های استاندارد بدن تخمین زده می‌شود
                تا الگو کامل ساخته شود. هر عددی را بعداً در دفترچه‌ی اندازه می‌توانید دقیق‌تر کنید.
            </p>
        </x-card>

        <x-advanced-section title="اندازه‌های تکمیلی" description="اگر عددهای دقیق را دارید، اینجا وارد کنید.">
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach (Measurements::optional() as $key => $field)
                    <x-field :label="$field['label']" :name="$key" suffix="س‌م">
                        <x-input type="number" step="0.5" :name="$key" :value="$measurements[$key] ?? null" />
                    </x-field>
                @endforeach
            </div>
        </x-advanced-section>
    </form>
</x-app-layout>
