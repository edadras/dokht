@php
    use App\Support\Jalali;

    $noteStyles = [
        'tip' => 'border-brand-200 bg-brand-50 text-brand-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];

    // داده‌های صفحه در یک بلوک JSON می‌آید تا هم متن فارسی خوانا بماند و هم
    // x-data کوتاه و قابل‌خواندن. همه‌اش از رجیستری آمده، نه از فهرست دستی.
    $styleMeta = [];
    $styleGroups = [];

    foreach ($catalogue['styles'] as $group => $row) {
        $styleGroups[$group] = array_keys($row['styles']);

        foreach ($row['styles'] as $key => $style) {
            $styleMeta[$key] = ['label' => $style['label'], 'group' => $group];
        }
    }

    /*
     * کارت‌های انتخابگر دیگر یک‌جا در صفحه نمی‌آیند.
     *
     * کاتالوگ ده‌ها هزار مدل دارد و فرستادنِ همه‌شان سه و نیم مگابایت بود — و با
     * هر مدلِ تازه بیشتر. حالا کنترلر بستهٔ اولِ هر نقش را می‌دهد و بقیه با
     * جستجو از سرور می‌آید، پس وزنِ صفحه دیگر به اندازهٔ کاتالوگ بستگی ندارد.
     */

    // «none» قطعه‌ای ندارد، پس بندانگشتی هم ندارد؛ بقیه از این الگو ساخته می‌شوند
    $thumbUrl = route('patterns.compose.thumb', ['group' => '__G__', 'key' => '__K__']);

    $roleTitles = [
        'garment' => 'لباس کامل',
        'bodice' => 'بالاتنه',
        'sleeve' => 'آستین',
        'lower' => 'پایین‌تنه',
        'collar' => 'یقه',
    ];

    $studio = [
        'styleMeta' => $styleMeta,
        'styleGroups' => $styleGroups,
        'roleTitles' => $roleTitles,
        'viewNames' => \App\Services\Pattern\GarmentFlatService::VIEWS,
        'picker' => $picker,
        'modelsUrl' => $modelsUrl,
        'thumbUrl' => $thumbUrl,
        'noteStyles' => $noteStyles,
        'previewUrl' => $previewUrl,
        'recipe' => $recipe,
        'initial' => $initial,
        'availability' => $availability,
    ];

    $fits = collect($availability)->filter(fn ($row) => $row['ok'])->count();
    $styleCount = count($availability);
@endphp

<x-app-layout title="کارگاه دوخت" :wide="true">
    <x-page-header title="کارگاه دوخت"
        subtitle="یک پایه انتخاب کنید، هر سبکی که می‌خواهید رویش بگذارید و اندازه را بدهید؛ الگوی کامل ساخته می‌شود."
        :back="route('patterns.index')" />

    {{-- پس از ساخت: گزارش کارهایی که برای دوختنی‌شدن لباس انجام شد، و راه ورود به الگو --}}
    @if ($composed = session('composed'))
        <x-card class="mb-6" icon="check-circle" :title="'ساخته شد: '.$composed['name']"
            :subtitle="\App\Support\Jalali::digits((string) $composed['pieces']).' قطعه الگو و '.\App\Support\Jalali::digits((string) $composed['cut_pieces']).' برش پارچه؛ هر تغییری خواستید همین‌جا بدهید و دوباره بسازید.'">
            <x-slot:actions>
                <x-button :href="$composed['url']" icon="eye" size="sm">باز کردن الگو</x-button>
            </x-slot:actions>

            <div class="space-y-2">
                @forelse ($composed['notes'] as $note)
                    <x-alert :type="in_array($note['type'], ['tip', 'info', 'warning', 'error'], true) ? $note['type'] : 'info'">
                        {{ $note['text'] }}
                    </x-alert>
                @empty
                    <x-alert type="tip">همه قطعه‌ها بدون هیچ تغییری با هم جور شدند.</x-alert>
                @endforelse
            </div>
        </x-card>
    @elseif ($reopened)
        <x-alert type="info" class="mb-6">این لباس از روی دستور یک الگوی ساخته‌شده باز شد؛ هر چیزی را عوض کنید و دوباره بسازید.</x-alert>
    @endif

    <script type="application/json" id="studio-data">@json($studio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>

    {{-- novalidate: پیش‌فرض بعضی پارامترها روی «گام» عددی مرورگر نمی‌افتد (مثلاً ۱.۲ با گام ۰.۵)
         و مرورگر جلوی فرستادن فرم را می‌گیرد؛ درستی مقدارها را سرور و خود سبک‌ها می‌سنجند. --}}
    <form method="POST" novalidate action="{{ route('patterns.compose.store') }}" @change="schedule()"
        @input.debounce.500ms="schedule()" x-data="{
            data: JSON.parse(document.getElementById('studio-data').textContent),
            kind: @js($recipe['kind'] ?? 'blocks'),
            base: {
                garment: @js($recipe['garment'] ?? null),
                bodice: @js($recipe['bodice'] ?? 'bodice_block'),
                sleeve: @js($recipe['sleeve'] ?? 'none'),
                lower: @js($recipe['lower'] ?? 'none'),
                collar: @js($recipe['collar'] ?? 'none'),
            },
            styles: [],
            params: { garment: {}, bodice: {}, sleeve: {}, lower: {}, collar: {} },
            availability: {},
            schemas: { roles: {}, styles: {} },
            paramsOf: {},
            q: { garment: '', bodice: '', sleeve: '', lower: '', collar: '' },
            modelsUrl: @js($modelsUrl),
            openGroups: {},
            svg: '', notes: [], pieces: [], metrics: {}, report: [], suggested: '',
            flats: { views: {}, measures: {}, notes: [], ok: false },
            error: null, busy: false, ready: false, timer: null, startBase: '',

            boot() {
                this.bootLists();
                this.availability = this.data.availability || {};
                this.schemas = (this.data.initial || {}).schemas || { roles: {}, styles: {} };
                this.startBase = this.baseKey();
                this.styles = (this.data.recipe.styles || []).map(style => ({
                    key: style.key,
                    side: style.side || 'both',
                    params: Object.assign({}, ((this.schemas.styles || {})[style.key] || {}).defaults || {}, style.params || {}),
                }));
                Object.keys(this.data.styleGroups).forEach((group, index) => {
                    this.openGroups[group] = index === 0 || this.chosenIn(group).length > 0;
                });
                this.syncParams();
                this.seed(this.data.initial || {});
            },
            seed(initial) {
                this.svg = initial.svg || '';
                this.flats = initial.flats || { views: {}, measures: {}, notes: [], ok: false };
                this.notes = initial.notes || [];
                this.pieces = initial.pieces || [];
                this.metrics = initial.metrics || {};
                this.suggested = initial.name || '';
                this.error = initial.error || null;
                this.ready = true;
            },

            digits(value) { return String(value ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); },
            viewName(key) { return (this.data.viewNames || {})[key] || key; },

            /* --- گام یک: پایه --- */
            setKind(kind) {
                this.kind = kind;
                // با رفتن به «یک لباس کامل» اگر هیچ لباسی انتخاب نشده، اولی برداشته می‌شود
                if (kind === 'garment' && !this.base.garment) {
                    const first = this.page('garment')[0];
                    if (first) { this.base.garment = first.k; this.remember('garment', first); }
                }
                this.$nextTick(() => this.schedule());
            },
            baseKey() {
                return this.kind === 'garment'
                    ? 'garment:' + this.base.garment
                    : 'blocks:' + [this.base.bodice, this.base.sleeve, this.base.lower, this.base.collar].join('+');
            },
            sameBase() { return this.startBase === this.baseKey(); },
            /* فهرست هر نقش بسته‌بسته از سرور می‌آید؛ صفحه بستهٔ اول را دارد و
               جستجو و بسته‌های بعدی را می‌خواهد */
            lists: {},
            pages: { garment: 1, bodice: 1, sleeve: 1, lower: 1, collar: 1 },
            listBusy: {},
            timers: {},
            tickets: {},

            bootLists() { this.lists = JSON.parse(JSON.stringify(this.data.picker)); },
            count(group) { return (this.lists[group] || {}).total || 0; },
            hint(item) { return item.h || ''; },
            thumb(group, key) {
                if (key === 'none') { return null; }
                return this.data.thumbUrl.replace('__G__', group).replace('__K__', encodeURIComponent(key));
            },
            fetchList(group, reset) {
                if (reset) { this.pages[group] = 1; }

                const page = this.pages[group];
                // پاسخ‌ها ممکن است نامرتب برسند؛ بستهٔ عقب‌مانده نباید نتیجهٔ
                // جستجوی تازه را پاک کند، پس هر درخواست نشانِ خودش را دارد
                const ticket = (this.tickets[group] || 0) + 1;
                this.tickets[group] = ticket;
                this.listBusy[group] = true;

                const url = this.data.modelsUrl + '?group=' + encodeURIComponent(group)
                    + '&q=' + encodeURIComponent(this.q[group] || '') + '&page=' + page;

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => {
                        if (!data || this.tickets[group] !== ticket) { return; }
                        const list = this.lists[group] || {};
                        list.rows = page === 1 ? data.rows : (list.rows || []).concat(data.rows);
                        list.total = data.total;
                        list.more = data.more;
                        this.lists[group] = list;
                    })
                    .finally(() => {
                        if (this.tickets[group] === ticket) { this.listBusy[group] = false; }
                    });
            },
            searchList(group) {
                clearTimeout(this.timers[group]);
                this.timers[group] = setTimeout(() => this.fetchList(group, true), 300);
            },
            olderList(group) { this.pages[group] = (this.pages[group] || 1) + 1; this.fetchList(group, false); },
            page(group) {
                const list = this.lists[group] || {};
                const rows = list.rows || [];
                // انتخابِ فعلی هرگز از فهرست بیرون نمی‌افتد، وگرنه کاربر نمی‌بیند
                // چه چیزی انتخاب کرده و کارت هیچ‌کدام روشن نیست
                if (this.base[group] && list.chosen && !rows.some(item => item.k === this.base[group])) {
                    return [list.chosen, ...rows];
                }
                return rows;
            },
            more(group) { return !!(this.lists[group] || {}).more; },
            shownCount(group) { return ((this.lists[group] || {}).rows || []).length; },
            // انتخاب باید یادمان بماند، وگرنه با یک جستجوی تازه از فهرست بیرون
            // می‌افتد و رادیوی انتخاب‌شده از صفحه پاک می‌شود — یعنی فرم بی‌پایه
            remember(group, item) {
                const list = this.lists[group];
                if (list) { list.chosen = item; }
            },

            /* --- گام دو: سبک‌ها --- */
            ok(key) { const row = this.availability[key]; return !row || row.ok; },
            reason(key) { return (this.availability[key] || {}).reason || ''; },
            styleLabel(key) { return (this.data.styleMeta[key] || {}).label || key; },
            styleSchema(key) { return ((this.schemas.styles || {})[key] || {}).schema || {}; },
            hasStyle(key) { return this.styles.some(style => style.key === key); },
            chosenIn(group) { return (this.data.styleGroups[group] || []).filter(key => this.hasStyle(key)); },
            fitCount(group) { return (this.data.styleGroups[group] || []).filter(key => this.ok(key)).length; },
            ordered() {
                const order = @js(App\Services\Pattern\PatternComposer::STYLE_ORDER);
                return [...this.styles].sort((a, b) => order.indexOf((this.data.styleMeta[a.key] || {}).group) - order.indexOf((this.data.styleMeta[b.key] || {}).group));
            },
            toggleStyle(key) {
                const at = this.styles.findIndex(style => style.key === key);
                if (at >= 0) { this.styles.splice(at, 1); }
                else { this.styles.push({ key: key, side: 'both', params: {} }); }
                this.$nextTick(() => this.schedule());
            },

            /* پارامترهای هر سبک وقتی توضیحش رسید با پیش‌فرض‌هایش پر می‌شود */
            syncStyleParams() {
                let changed = false;
                this.styles.forEach(style => {
                    const defaults = ((this.schemas.styles || {})[style.key] || {}).defaults || {};
                    Object.entries(defaults).forEach(([key, value]) => {
                        if (style.params[key] === undefined || style.params[key] === null) {
                            style.params[key] = value === true ? '1' : (value === false ? '0' : value);
                            changed = true;
                        }
                    });
                });
                return changed;
            },

            /* --- تنظیمات حرفه‌ای --- */
            activeRoles() {
                return Object.entries(this.schemas.roles || {}).map(([role, block]) => ({
                    role: role,
                    key: block.key,
                    title: this.data.roleTitles[role] || role,
                    label: block.label,
                    schema: block.schema || {},
                    defaults: block.defaults || {},
                }));
            },
            /*
             * اندازه‌های لباس در برابر تنظیم‌های ریز.
             *
             * هر دو از همان «توضیح پارامترها»ی خودِ مدل می‌آیند، نه از فهرست
             * دستی. فرقشان این است که خیاط قد لباس و قد آستین را همیشه می‌خواهد
             * ببیند و دست بزند، ولی شیب سرشانه و گودی حلقه را فقط وقتی لازم
             * می‌شود. پس اولی‌ها می‌آیند جلو و بقیه در بخش حرفه‌ای می‌مانند.
             *
             * جداکردن روی *کلید* است نه برچسب، چون کلیدها ثابت‌اند و برچسب‌ها
             * ممکن است عوض شوند.
             */
            isSize(key) { return /(length|flare|height|width|rise|vent|stand|pleat)/.test(key); },
            sizeFields(role) { return this.fieldsOf(role.schema, role.defaults).filter(f => this.isSize(f.key)); },
            fineFields(role) { return this.fieldsOf(role.schema, role.defaults).filter(f => ! this.isSize(f.key)); },
            anySizeFields() { return this.activeRoles().some(role => this.sizeFields(role).length > 0); },

            fieldsOf(schema, values) {
                return Object.entries(schema || {}).map(([key, field]) => ({
                    key: key,
                    label: field.label || key,
                    type: field.type === 'select' ? 'select' : (field.type === 'toggle' ? 'toggle' : 'number'),
                    min: field.min ?? null, max: field.max ?? null, step: field.step ?? 0.5,
                    hint: field.hint || (field.unit ? 'واحد: ' + field.unit : ''),
                    options: Object.entries(field.options || {}).map(([value, label]) => ({ value: value, label: label })),
                }));
            },
            syncParams() {
                let changed = false;
                this.activeRoles().forEach(role => {
                    // با عوض‌شدن مدلِ یک نقش، پارامترهای مدل قبلی دور ریخته می‌شود
                    const current = this.paramsOf[role.role] === role.key ? (this.params[role.role] || {}) : {};
                    this.paramsOf[role.role] = role.key;
                    const next = {};
                    Object.keys(role.schema || {}).forEach(key => {
                        next[key] = current[key] !== undefined && current[key] !== '' ? current[key] : (role.defaults || {})[key];
                        if (next[key] === true) { next[key] = '1'; }
                        if (next[key] === false) { next[key] = '0'; }
                    });
                    changed = changed || JSON.stringify(next) !== JSON.stringify(this.params[role.role] || {});
                    this.params[role.role] = next;
                });
                return changed;
            },

            /* --- پیش‌نمایش زنده --- */
            schedule() {
                this.syncParams();
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refresh(), 350);
            },
            async refresh() {
                this.busy = true;
                let stale = false;
                const query = new URLSearchParams(new FormData(this.$root));
                query.delete('_token');

                try {
                    const response = await fetch(this.data.previewUrl + '?' + query.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const body = await response.json();
                    this.availability = body.availability || this.availability;
                    this.schemas = body.schemas || this.schemas;
                    // توضیح پارامترها همراه پیش‌نمایش می‌آید؛ اگر مقدار تازه‌ای نشست، یک‌بار دیگر می‌سازیم
                    stale = [this.syncParams(), this.syncStyleParams()].some(Boolean);

                    if (!response.ok) {
                        this.error = body.message || 'این ترکیب ساخته نمی‌شود.';
                        this.svg = ''; this.notes = []; this.pieces = []; this.report = [];
                        this.flats = { views: {}, measures: {}, notes: [], ok: false };
                    } else {
                        this.error = null;
                        this.svg = body.svg;
                        this.flats = body.flats || { views: {}, measures: {}, notes: [], ok: false };
                        this.notes = body.notes || [];
                        this.pieces = body.pieces || [];
                        this.metrics = body.metrics || {};
                        this.report = body.styles || [];
                        this.suggested = body.name || '';
                    }
                } catch (failure) {
                    this.error = 'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.';
                }

                this.busy = false;
                this.ready = true;

                if (stale) { this.schedule(); }
            },
        }" x-init="boot()" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
        @csrf
        <input type="hidden" name="kind" :value="kind">

        <div class="min-w-0 space-y-6">
            {{-- گام ۱: پایه --}}
            <x-card title="۱. پایه لباس" icon="shirt"
                subtitle="یا یک لباس آماده را بردارید، یا خودتان از بالاتنه و آستین و پایین‌تنه بسازید.">
                <div class="space-y-5">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="setKind('garment')"
                            :class="kind === 'garment' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                            class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                            یک لباس کامل
                        </button>
                        <button type="button" @click="setKind('blocks')"
                            :class="kind === 'blocks' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                            class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                            از بلوک بساز
                        </button>
                    </div>

                    <div x-show="kind === 'garment'" x-cloak>
                        @include('patterns.partials.compose-picker', [
                            'group' => 'garment',
                            'title' => 'یک لباس کامل',
                            'subtitle' => 'همه قطعه‌های لباس از همین مدل می‌آید و سبک‌ها رویش می‌نشیند.',
                            'icon' => 'shirt',
                            'enabled' => "kind === 'garment'",
                        ])
                    </div>

                    <div x-show="kind === 'blocks'" x-cloak class="space-y-6">
                        @include('patterns.partials.compose-picker', [
                            'group' => 'bodice',
                            'title' => 'بالاتنه',
                            'subtitle' => 'پایه لباس؛ حتماً باید انتخاب شود.',
                            'icon' => 'shirt',
                            'enabled' => "kind === 'blocks'",
                        ])

                        @include('patterns.partials.compose-picker', [
                            'group' => 'sleeve',
                            'title' => 'آستین',
                            'subtitle' => 'سرآستین خودکار با حلقه آستین همین بالاتنه جور می‌شود.',
                            'icon' => 'scissors',
                            'enabled' => "kind === 'blocks'",
                        ])

                        @include('patterns.partials.compose-picker', [
                            'group' => 'lower',
                            'title' => 'پایین‌تنه',
                            'subtitle' => 'دامن یا شلوار — فقط یکی؛ در خط کمر به بالاتنه دوخته می‌شود.',
                            'icon' => 'ruler',
                            'enabled' => "kind === 'blocks'",
                        ])

                        @include('patterns.partials.compose-picker', [
                            'group' => 'collar',
                            'title' => 'یقه دوخته‌شده',
                            'subtitle' => 'به اندازه خط یقه همین ترکیب بریده می‌شود (سبک‌های خط یقه جدا هستند).',
                            'icon' => 'stitch',
                            'enabled' => "kind === 'blocks'",
                        ])
                    </div>
                </div>
            </x-card>

            {{-- گام ۲: سبک‌ها --}}
            @include('patterns.partials.compose-styles')

            @include('patterns.partials.compose-sizes')

            {{-- گام ۳: اندازه‌ها --}}
            <div x-data="{ source: @js(old('customer_id') ? 'customer' : 'size') }">
                <x-card title="۴. اندازه‌ها برای چه کسی؟" icon="user"
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
                                    :options="collect($sizes)->mapWithKeys(fn ($size) => [$size => 'سایز '.Jalali::digits($size)])"
                                    :selected="old('base_size', '40')" />
                            </x-field>

                            <x-field label="نام الگو" name="name" hint="خالی بگذارید تا نام ترکیب گذاشته شود.">
                                <x-input name="name" placeholder="مثلاً پیراهن مجلسی خانم رضایی" />
                            </x-field>
                        </div>
                    </div>
                </x-card>
            </div>

            @include('patterns.partials.compose-advanced')
        </div>

        {{-- پیش‌نمایش زنده --}}
        <aside class="space-y-4 xl:sticky xl:top-24">
            <x-card title="لباس دوخته‌شده" icon="shirt" padding="p-4"
                subtitle="از چهار طرف، با همین اندازه‌ها.">
                @include('patterns.partials.garment-flats-live')
            </x-card>

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

                            <div class="flex flex-wrap items-center gap-2 text-xs text-stone-500">
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 font-semibold text-stone-700"
                                    x-text="digits(pieces.length) + ' قطعه الگو'"></span>
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 font-semibold text-stone-700"
                                    x-text="digits(metrics.cut_pieces || 0) + ' برش پارچه'"></span>
                                <span x-show="styles.length" class="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700"
                                    x-text="digits(report.filter(row => row.status === 'applied').length) + ' سبک اجرا شد'"></span>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="piece in pieces" :key="piece.code">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                                        <span x-text="piece.name"></span>
                                        <span class="text-stone-400" x-text="'×' + digits(piece.cut_quantity)"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>

            <x-card padding="p-4">
                <div x-show="sameBase()">
                    <x-meter label="سبک‌های سازگار با این پایه" :value="$fits" :max="max(1, $styleCount)" />
                </div>

                <p class="text-xs text-stone-500" :class="sameBase() && 'mt-2'">
                    <span x-text="digits(Object.values(availability).filter(row => row.ok).length)">{{ Jalali::digits((string) $fits) }}</span>
                    سبک از {{ Jalali::digits((string) $styleCount) }} سبک کاتالوگ روی این پایه می‌نشیند؛
                    بقیه با دلیلشان خاموش نشان داده می‌شوند.
                </p>
            </x-card>

            {{-- گزارش کارهایی که برای جورشدن قطعه‌ها انجام شد --}}
            <template x-if="notes.length">
                <div class="space-y-2">
                    <template x-for="(note, index) in notes" :key="index">
                        <div class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                            :class="data.noteStyles[note.type] || data.noteStyles.info">
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
