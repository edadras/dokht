@php
    $noteStyles = [
        'tip' => 'border-brand-200 bg-brand-50 text-brand-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];
@endphp

<x-app-layout title="ترکیب مدل" :wide="true">
    <x-page-header title="ترکیب مدل"
        subtitle="بالاتنه، آستین، پایین‌تنه و یقه را جدا انتخاب کنید؛ یک الگوی یکپارچه ساخته می‌شود."
        :back="route('patterns.index')" />

    <form method="POST" action="{{ route('patterns.compose.store') }}" @change="schedule()"
        @input.debounce.500ms="schedule()"
        x-data="{
            selection: {
                bodice: @js(old('bodice', $selection['bodice'])),
                sleeve: @js(old('sleeve', $selection['sleeve'] ?? 'none')),
                lower: @js(old('lower', $selection['lower'] ?? 'none')),
                collar: @js(old('collar', $selection['collar'] ?? 'none')),
            },
            svg: '',
            notes: [],
            pieces: [],
            metrics: {},
            suggested: '',
            error: null,
            busy: false,
            ready: false,
            timer: null,
            noteStyles: @js($noteStyles),
            schedule() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refresh(), 350);
            },
            async refresh() {
                this.busy = true;
                const query = new URLSearchParams(new FormData(this.$root));
                query.delete('_token');

                try {
                    const response = await fetch(@js($previewUrl) + '?' + query.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const body = await response.json();

                    if (!response.ok) {
                        this.error = body.message || 'این ترکیب ساخته نمی‌شود.';
                        this.svg = '';
                        this.notes = [];
                        this.pieces = [];
                    } else {
                        this.error = null;
                        this.svg = body.svg;
                        this.notes = body.notes || [];
                        this.pieces = body.pieces || [];
                        this.metrics = body.metrics || {};
                        this.suggested = body.name || '';
                    }
                } catch (failure) {
                    this.error = 'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.';
                }

                this.busy = false;
                this.ready = true;
            },
        }" x-init="refresh()" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        @csrf

        <div class="min-w-0 space-y-6">
            @include('patterns.partials.compose-picker', [
                'group' => 'bodice',
                'title' => '۱. بالاتنه',
                'subtitle' => 'پایه لباس؛ حتماً باید انتخاب شود.',
                'icon' => 'shirt',
                'items' => $options['bodice'],
                'selected' => $selection['bodice'],
            ])

            @include('patterns.partials.compose-picker', [
                'group' => 'sleeve',
                'title' => '۲. آستین',
                'subtitle' => 'سرآستین خودکار با حلقه آستین همین بالاتنه جور می‌شود.',
                'icon' => 'scissors',
                'items' => $options['sleeve'],
                'selected' => $selection['sleeve'] ?? 'none',
            ])

            @include('patterns.partials.compose-picker', [
                'group' => 'lower',
                'title' => '۳. پایین‌تنه',
                'subtitle' => 'دامن یا شلوار — فقط یکی؛ در خط کمر به بالاتنه دوخته می‌شود.',
                'icon' => 'ruler',
                'items' => $options['lower'],
                'selected' => $selection['lower'] ?? 'none',
            ])

            @include('patterns.partials.compose-picker', [
                'group' => 'collar',
                'title' => '۴. یقه',
                'subtitle' => 'به اندازه خط یقه همین ترکیب بریده می‌شود.',
                'icon' => 'stitch',
                'items' => $options['collar'],
                'selected' => $selection['collar'] ?? 'none',
            ])

            {{-- اندازه‌ها: یک منبع، یا مشتری یا سایز استاندارد --}}
            <div x-data="{ source: @js(old('customer_id') ? 'customer' : 'size') }">
                <x-card title="۵. اندازه‌ها برای چه کسی؟" icon="user"
                    subtitle="اندازه‌های نداشته خودکار تخمین زده می‌شود.">
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="source = 'customer'; $nextTick(() => schedule())"
                            :class="source === 'customer' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                            class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                            اندازه‌های یک مشتری
                        </button>
                        <button type="button" @click="source = 'size'; $nextTick(() => schedule())"
                            :class="source === 'size' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                            class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                            سایز استاندارد
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div x-show="source === 'customer'" x-cloak>
                            <x-field label="مشتری" name="customer_id" hint="اندازه پیش‌فرض مشتری استفاده می‌شود.">
                                <x-select name="customer_id" placeholder="انتخاب مشتری" :selected="old('customer_id')"
                                    x-bind:disabled="source !== 'customer'">
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">
                                            {{ $customer->name }}
                                            @if ($customer->defaultMeasurementSet)
                                                — {{ $customer->defaultMeasurementSet->summary() }}
                                            @else
                                                — بدون اندازه ثبت‌شده
                                            @endif
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>

                        <x-field label="سایز مبنا" name="base_size">
                            <x-select name="base_size"
                                :options="collect($sizes)->mapWithKeys(fn ($size) => [$size => 'سایز '.\App\Support\Jalali::digits($size)])"
                                :selected="old('base_size', '40')" />
                        </x-field>

                        <x-field label="نام الگو" name="name" hint="خالی بگذارید تا نام ترکیب گذاشته شود.">
                            <x-input name="name" placeholder="مثلاً پیراهن مجلسی خانم رضایی" />
                        </x-field>
                    </div>
                </div>
                </x-card>
            </div>

            {{-- همه چیز حرفه‌ای اینجا پنهان است --}}
            <x-advanced-section title="تنظیمات حرفه‌ای"
                description="آزادی لباس، چین کمر، جای دوخت و پارامترهای هر بلوک — همه پیش‌فرض مناسب دارند.">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-3 text-sm font-bold text-stone-700">آزادی لباس (سانتی‌متر)</h3>
                        <div class="grid gap-4 sm:grid-cols-4">
                            @foreach (['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو'] as $key => $label)
                                <x-field :label="$label" :name="'ease.'.$key">
                                    <x-input type="number" step="0.5" :name="'ease['.$key.']'"
                                        :value="old('ease.'.$key)" placeholder="پیش‌فرض" />
                                </x-field>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-stone-500">مقدار منفی برای پارچه‌های کشی مجاز است.</p>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-bold text-stone-700">دوخت کمر</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="چین کمر پایین‌تنه" name="gather"
                                hint="پایین‌تنه این‌قدر گشادتر بریده و روی کمر چین داده می‌شود.">
                                <x-input type="number" step="1" min="0" max="40" name="gather"
                                    :value="old('gather')" placeholder="۰" />
                            </x-field>

                            <x-field label="اگر کمرها هم‌اندازه نبودند" name="waist_join">
                                <x-select name="waist_join" :selected="old('waist_join', 'auto')" :options="[
                                    'auto' => 'خودکار (بهترین راه)',
                                    'gather' => 'چین دادن',
                                    'true_side_seams' => 'راست‌سازی درز پهلو',
                                ]" />
                            </x-field>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-bold text-stone-700">جای دوخت هر لبه (سانتی‌متر)</h3>
                        <div class="grid gap-4 sm:grid-cols-4">
                            @foreach ($seamTags as $tag => $label)
                                <x-field :label="$label" :name="'seam_allowances.'.$tag">
                                    <x-input type="number" step="0.1" min="0" max="10"
                                        :name="'seam_allowances['.$tag.']'"
                                        :value="old('seam_allowances.'.$tag, $defaultSeamAllowances[$tag] ?? null)" />
                                </x-field>
                            @endforeach
                        </div>
                    </div>

                    @foreach (['bodice' => 'بالاتنه', 'sleeve' => 'آستین', 'lower' => 'پایین‌تنه', 'collar' => 'یقه'] as $group => $groupLabel)
                        <div class="space-y-4">
                            <h3 class="text-sm font-bold text-stone-700">پارامترهای {{ $groupLabel }}</h3>

                            @foreach ($schemas[$group] as $key => $block)
                                @continue(! $block)

                                <div x-show="selection.{{ $group }} === '{{ $key }}'" x-cloak class="space-y-3">
                                    <p class="text-xs text-stone-500">{{ $block['label'] }}</p>

                                    <div class="grid gap-4 sm:grid-cols-3">
                                        @foreach ($block['schema'] as $param => $field)
                                            @php
                                                $value = old('params.'.$group.'.'.$param, $block['defaults'][$param] ?? null);
                                                $isToggle = ($field['type'] ?? 'number') === 'toggle';
                                                $inputName = 'params['.$group.']['.$param.']';
                                            @endphp

                                            <x-field :label="$field['label'] ?? $param" :hint="$field['hint'] ?? null">
                                                @if ($isToggle)
                                                    <select name="{{ $inputName }}"
                                                        x-bind:disabled="selection.{{ $group }} !== '{{ $key }}'"
                                                        class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                                        <option value="1" @selected($value)>دارد</option>
                                                        <option value="0" @selected(! $value)>ندارد</option>
                                                    </select>
                                                @else
                                                    <x-input type="number" :name="$inputName" :value="$value"
                                                        :step="$field['step'] ?? 0.5" :min="$field['min'] ?? null"
                                                        :max="$field['max'] ?? null" :suffix="$field['unit'] ?? null"
                                                        x-bind:disabled="selection.{{ $group }} !== '{{ $key }}'" />
                                                @endif
                                            </x-field>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            <p x-show="! ['{{ implode("','", array_keys(array_filter($schemas[$group]))) }}'].includes(selection.{{ $group }})"
                                x-cloak class="text-xs text-stone-500">
                                برای {{ $groupLabel }} چیزی انتخاب نشده است.
                            </p>
                        </div>
                    @endforeach

                    <x-field label="یادداشت" name="notes">
                        <x-textarea name="notes" rows="2" placeholder="نکته‌ای که می‌خواهید روی این الگو بماند…" />
                    </x-field>
                </div>
            </x-advanced-section>
        </div>

        {{-- پیش‌نمایش زنده --}}
        <aside class="space-y-4 xl:sticky xl:top-24">
            <x-card title="پیش‌نمایش" icon="eye" padding="p-4">
                <x-slot:actions>
                    <span x-show="busy" x-cloak class="text-xs text-stone-400">در حال ساخت…</span>
                </x-slot:actions>

                <div class="space-y-3">
                    <div class="min-h-40 overflow-auto rounded-xl border border-stone-100 bg-stone-50 p-2 [&>svg]:h-auto [&>svg]:w-full"
                        x-html="svg"></div>

                    <template x-if="error">
                        <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                            <span class="font-medium" x-text="error"></span>
                        </div>
                    </template>

                    <template x-if="! error && ready">
                        <div class="space-y-2">
                            <p class="text-sm font-bold text-stone-800" x-text="suggested"></p>

                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="piece in pieces" :key="piece.code">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                                        <span x-text="piece.name"></span>
                                        <span class="text-stone-400" x-text="'×' + piece.cut_quantity"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>

            {{-- گزارش کارهایی که برای جورشدن قطعه‌ها انجام شد --}}
            <template x-if="notes.length">
                <div class="space-y-2">
                    <template x-for="(note, index) in notes" :key="index">
                        <div class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                            :class="noteStyles[note.type] || noteStyles.info">
                            <span class="font-medium" x-text="note.text"></span>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="ready && ! notes.length && ! error">
                <x-alert type="tip">همه قطعه‌ها بدون تغییر با هم جور شدند.</x-alert>
            </template>

            <x-button type="submit" size="lg" icon="sparkles" class="w-full">بساز</x-button>

            <p class="text-center text-xs text-stone-500">الگوی ساخته‌شده در ویرایشگر، چاپ و خروجی مثل بقیه الگوها باز می‌شود.</p>
        </aside>
    </form>
</x-app-layout>
