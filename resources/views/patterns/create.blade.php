<x-app-layout title="الگوی جدید">
    <x-page-header title="الگوی جدید" subtitle="مدل را انتخاب کنید و بگویید برای چه کسی است؛ بقیه کارها خودکار انجام می‌شود."
        :back="route('patterns.index')" />

    {{-- فهرست مدل‌ها در یک بلوک JSON می‌آید و مرورگر کارت‌ها را می‌سازد.
         با هزاران مدل، نوشتنِ همهٔ کارت‌ها در خودِ صفحه یعنی صفحه‌ای که باز
         نمی‌شود؛ این‌جا فقط یک بسته نشان داده می‌شود و جستجو روی همه کار می‌کند. --}}
    <script type="application/json" id="template-list">@json($templateCards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>

    <form method="POST" action="{{ route('patterns.store') }}"
        x-data="{
            template: @js($selectedTemplate),
            source: @js(old('customer_id') ? 'customer' : 'size'),
            first: JSON.parse(document.getElementById('template-list').textContent),
            chosen: @js($selectedCard),
            searchUrl: @js($templateSearchUrl),
            previewUrl: @js($templatePreviewUrl),
            digits(value) { return String(value ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); },
            thumb(id) { return this.previewUrl.replace('__ID__', id); },
            rows: [], total: 0, hasMore: false, page: 1, q: '', busy: false, timer: null,

            /* فهرست از سرور می‌آید، نه از خودِ صفحه: کاتالوگ ده‌ها هزار مدل
               دارد و فرستادنِ همه‌اش یعنی صفحه‌ای که با هر مدلِ تازه سنگین‌تر
               می‌شود. صفحه یک بسته می‌گیرد و بقیه را با جستجو می‌خواهد. */
            boot() {
                this.rows = this.first.rows;
                this.total = this.first.total;
                this.hasMore = this.first.more;
            },
            find(reset) {
                if (reset) { this.page = 1; }
                this.busy = true;

                const url = this.searchUrl + '?q=' + encodeURIComponent(this.q) + '&page=' + this.page;

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => {
                        if (! data) { return; }
                        this.rows = this.page === 1 ? data.rows : this.rows.concat(data.rows);
                        this.total = data.total;
                        this.hasMore = data.more;
                    })
                    .finally(() => { this.busy = false; });
            },
            search() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.find(true), 300);
            },
            older() { this.page += 1; this.find(false); },

            page_() { return this.rows; },
            // مدلِ انتخاب‌شده همیشه در فهرست هست، حتی وقتی جستجو کنارش گذاشته
            cards() {
                if (this.chosen && ! this.rows.some(row => row.i === this.chosen.i)) {
                    return [this.chosen, ...this.rows];
                }
                return this.rows;
            },
            more() { return this.hasMore; },

            params: { name: '', description: '', schema: {}, defaults: {} },
            paramsUrl: @js($templateParamsUrl),
            loadParams() {
                if (! this.template) { this.params = { name: '', description: '', schema: {}, defaults: {} }; return; }
                fetch(this.paramsUrl.replace('__ID__', this.template), { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => { this.params = data || { name: '', description: '', schema: {}, defaults: {} }; })
                    .catch(() => { this.params = { name: '', description: '', schema: {}, defaults: {} }; });
            },
        }" x-init="boot()" x-effect="template, loadParams()" class="space-y-6">
        @csrf

        {{-- گام یک: انتخاب مدل از کتابخانه --}}
        <x-card title="۱. مدل الگو را انتخاب کنید" icon="book"
            subtitle="مدل‌های پایه آماده‌اند؛ اندازه‌ها روی همان مدل پیاده می‌شود.">
            @if (empty($templateCards['rows']))
                <x-alert type="warning">
                    هنوز الگوی پایه‌ای در کتابخانه نیست. از مدیر سامانه بخواهید کتابخانه الگوها را پر کند.
                </x-alert>
            @endif

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-stone-500">
                    <span x-text="digits(total)"></span> مدل در کتابخانه
                </p>

                <label class="sr-only" for="template-search">جستجوی مدل</label>
                <input id="template-search" type="search" x-model="q" @input="search()"
                    placeholder="جستجوی نام مدل…"
                    class="w-56 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="row in cards()" :key="row.i">
                    <label class="cursor-pointer">
                        <input type="radio" name="pattern_template_id" x-bind:value="row.i" class="peer sr-only"
                            x-model.number="template" required>

                        <div class="h-full rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300">
                            <div class="flex h-32 items-center justify-center overflow-hidden rounded-xl bg-stone-50">
                                <img loading="lazy" decoding="async" x-bind:src="thumb(row.i)" x-bind:alt="row.n"
                                    class="h-full w-auto object-contain">
                            </div>

                            <p class="mt-3 font-bold text-stone-900" x-text="row.n"></p>
                            <p class="text-xs text-brand-600" x-show="row.g" x-text="row.g"></p>
                        </div>
                    </label>
                </template>
            </div>

            {{-- توضیحِ مدلِ انتخاب‌شده؛ روی *همهٔ* کارت‌ها نمی‌آید چون برای هر
                 مدل یکتاست و با هزاران مدل، دو مگابایت متن به صفحه اضافه می‌کرد --}}
            <p x-show="params.description" x-cloak
                class="mt-4 rounded-xl bg-stone-50 px-4 py-3 text-xs leading-6 text-stone-600"
                x-text="params.description"></p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" x-show="more()" x-cloak @click="older()" x-bind:disabled="busy"
                    class="rounded-xl border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:border-brand-400 hover:text-brand-700 disabled:opacity-50">
                    مدل‌های بیشتر
                </button>

                <p x-show="more()" x-cloak class="text-xs text-stone-400">
                    <span x-text="digits(total - rows.length)"></span>
                    مدل دیگر هم هست؛ با جستجوی نام زودتر پیدا می‌شود.
                </p>

                <p x-show="q && ! busy && rows.length === 0" x-cloak class="text-xs font-medium text-amber-700">
                    هیچ مدلی با این نام پیدا نشد.
                </p>
            </div>

            @error('pattern_template_id')
                <p class="mt-3 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </x-card>

        {{-- گام دو: برای چه کسی --}}
        <x-card title="۲. الگو برای چه کسی است؟" icon="user"
            subtitle="اگر مشتری اندازه ثبت‌شده داشته باشد، از همان استفاده می‌شود؛ وگرنه یک سایز استاندارد کافی است.">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="source = 'customer'"
                        :class="source === 'customer' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                        class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                        اندازه‌های یک مشتری
                    </button>
                    <button type="button" @click="source = 'size'"
                        :class="source === 'size' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                        class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                        سایز استاندارد
                    </button>
                </div>

                <div x-show="source === 'customer'" x-cloak class="grid gap-4 sm:grid-cols-2">
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

                    <x-field label="سایز مبنا" name="base_size" hint="برای سایزبندی بعدی استفاده می‌شود.">
                        <x-select name="base_size" :options="collect($sizes)->mapWithKeys(fn ($size) => [$size => 'سایز '.\App\Support\Jalali::digits($size)])"
                            :selected="old('base_size', '40')" />
                    </x-field>
                </div>

                <div x-show="source === 'size'" x-cloak>
                    <x-field label="سایز استاندارد" name="base_size">
                        <x-select name="base_size" :options="collect($sizes)->mapWithKeys(fn ($size) => [$size => 'سایز '.\App\Support\Jalali::digits($size)])"
                            :selected="old('base_size', '40')" x-bind:disabled="source !== 'size'" />
                    </x-field>
                </div>

                <x-field label="نام الگو" name="name" hint="اگر خالی بماند نام مدل گذاشته می‌شود.">
                    <x-input name="name" placeholder="مثلاً پیراهن خانم رضایی" />
                </x-field>
            </div>
        </x-card>

        {{-- تنظیمات حرفه‌ای: پیش‌فرض‌ها درست‌اند و لازم نیست کاربر ساده بازشان کند --}}
        <x-advanced-section title="تنظیمات حرفه‌ای" description="آزادی، جای دوخت و پارامترهای مدل — همه پیش‌فرض مناسب دارند.">
            <div class="space-y-6">
                <div>
                    <h3 class="mb-3 text-sm font-bold text-stone-700">آزادی لباس (سانتی‌متر)</h3>
                    <div class="grid gap-4 sm:grid-cols-4">
                        @foreach (['bust' => 'دور سینه', 'waist' => 'دور کمر', 'hip' => 'دور باسن', 'bicep' => 'دور بازو'] as $key => $label)
                            <x-field :label="$label" :name="'ease.'.$key">
                                <x-input type="number" step="0.5" :name="'ease['.$key.']'"
                                    :value="old('ease.'.$key)" placeholder="پیش‌فرض مدل" />
                            </x-field>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-stone-500">مقدار منفی برای پارچه‌های کشی مجاز است.</p>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-bold text-stone-700">جای دوخت هر لبه (سانتی‌متر)</h3>
                    <div class="grid gap-4 sm:grid-cols-4">
                        @foreach (\App\Services\Pattern\SeamAllowanceService::TAGS as $tag => $label)
                            <x-field :label="$label" :name="'seam_allowances.'.$tag">
                                <x-input type="number" step="0.1" min="0" max="10" :name="'seam_allowances['.$tag.']'"
                                    :value="old('seam_allowances.'.$tag, $defaultSeamAllowances[$tag] ?? null)" />
                            </x-field>
                        @endforeach
                    </div>
                </div>

                {{-- پارامترهای همان مدلی که انتخاب شده، نه همهٔ مدل‌های کتابخانه:
                     با هزاران مدل، فرمِ پنهانِ همه یعنی ده‌ها هزار فیلد در یک صفحه --}}
                <div class="space-y-5">
                    <h3 class="text-sm font-bold text-stone-700">پارامترهای مدل انتخاب‌شده</h3>

                    <template x-if="template && params.name">
                        <div class="space-y-4">
                            <p class="text-xs text-stone-500" x-text="params.name"></p>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <template x-for="(field, key) in params.schema" :key="key">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-stone-700"
                                            x-text="field.label || key"></label>

                                        <template x-if="field.type === 'toggle'">
                                            <select x-bind:name="'params[' + key + ']'"
                                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                                <option value="1" x-bind:selected="!! params.defaults[key]">دارد</option>
                                                <option value="0" x-bind:selected="! params.defaults[key]">ندارد</option>
                                            </select>
                                        </template>

                                        <template x-if="field.type === 'select'">
                                            <select x-bind:name="'params[' + key + ']'"
                                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                                <template x-for="(label, value) in (field.options || {})" :key="value">
                                                    <option x-bind:value="value" x-text="label"
                                                        x-bind:selected="params.defaults[key] === value"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="field.type !== 'toggle' && field.type !== 'select'">
                                            <input type="number" x-bind:name="'params[' + key + ']'"
                                                x-bind:value="params.defaults[key]"
                                                x-bind:step="field.step || 0.5"
                                                x-bind:min="field.min" x-bind:max="field.max"
                                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                        </template>

                                        <p class="mt-1 text-xs text-stone-500" x-show="field.hint" x-text="field.hint"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <p x-show="! template" class="text-xs text-stone-500">اول یک مدل انتخاب کنید.</p>
                </div>

                <x-field label="یادداشت" name="notes">
                    <x-textarea name="notes" rows="2" placeholder="نکته‌ای که می‌خواهید روی این الگو بماند…" />
                </x-field>
            </div>
        </x-advanced-section>

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-stone-500">اندازه‌های خالی خودکار تخمین زده می‌شوند.</p>
            <x-button type="submit" size="lg" icon="sparkles">ساخت الگو</x-button>
        </div>
    </form>
</x-app-layout>
