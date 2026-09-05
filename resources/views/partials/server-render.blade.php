@php
    $serverRender = $serverRender ?? ['status' => 'none'];
    $serverRenderStatusUrl = $serverRenderStatusUrl ?? null;
    $serverRenderTitle = $serverRenderTitle ?? 'خروجی واقعی موتور سرور';
    $renderLabels = [
        'front' => 'نمای جلو',
        'side' => 'نمای پهلو',
        'back' => 'نمای پشت',
        'water' => 'آزمون وزن آب',
        'airflow' => 'آزمون جریان هوا',
    ];
@endphp

<x-card :title="$serverRenderTitle" icon="cube"
    subtitle="دوخت، افت پارچه، تصاویر و مدل سه‌بعدی در موتور Blender سرور ساخته می‌شوند؛ مرورگر فقط نتیجهٔ آماده را نمایش می‌دهد.">
    <div x-data="{
            status: @js($serverRender['status'] ?? 'none'),
            images: @js($serverRender['images'] ?? []),
            sheet: @js($serverRender['sheet'] ?? null),
            model: @js($serverRender['model'] ?? null),
            statusUrl: @js($serverRenderStatusUrl),
            labels: @js($renderLabels),
            active: @js(! empty($serverRender['model']) ? 'model' : 'front'),
            async poll() {
                if (this.status !== 'pending' || ! this.statusUrl) { return; }

                try {
                    const response = await fetch(this.statusUrl, {
                        headers: { Accept: 'application/json' },
                    });
                    const data = response.ok ? await response.json() : null;

                    if (data && data.status) {
                        this.status = data.status;
                        this.images = data.images || {};
                        this.sheet = data.sheet || null;
                        this.model = data.model || null;

                        const activeExists = (this.active === 'model' && this.model)
                            || (this.active === 'sheet' && this.sheet)
                            || this.images[this.active];
                        if (! activeExists) {
                            this.active = this.model
                                ? 'model'
                                : (Object.keys(this.images)[0] || (this.sheet ? 'sheet' : 'front'));
                        }
                    }
                } catch (error) {}

                if (this.status === 'pending') {
                    setTimeout(() => this.poll(), 8000);
                }
            },
            imageUrl() {
                return this.active === 'sheet' ? this.sheet : (this.images[this.active] || this.images.front || null);
            },
        }" x-init="poll()">
        <template x-if="status === 'ready' || Object.keys(images).length > 0 || model || sheet">
            <div class="space-y-4">
                <div x-show="status === 'pending'" x-cloak
                    class="flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-xs font-medium text-brand-900">
                    <x-icon name="refresh" class="h-4 w-4 shrink-0 animate-spin text-brand-600" />
                    رندر در حال تکمیل است؛ هر نمای آماده‌شده همین‌جا اضافه و به‌روزرسانی می‌شود.
                </div>
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-stone-950 shadow-sm">
                    <div x-show="active === 'model' && model" x-cloak
                        x-data="serverModelViewer()" x-effect="load(model)"
                        @destroy="destroy()"
                        class="relative aspect-4/5 w-full sm:aspect-video">
                        <canvas x-ref="canvas" class="block h-full w-full"
                            aria-label="مدل سه‌بعدی ساخته‌شده روی سرور"></canvas>

                        <div x-show="! ready && ! failed" x-cloak
                            class="absolute inset-0 flex items-center justify-center gap-2 text-sm text-stone-300">
                            <x-icon name="refresh" class="h-5 w-5 animate-spin" />
                            در حال باز کردن مدل سرور…
                        </div>
                        <div x-show="failed" x-cloak
                            class="absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-amber-200">
                            <span x-text="message"></span>
                        </div>
                        <div x-show="ready" x-cloak class="absolute bottom-3 end-3 flex gap-2">
                            <button type="button" @click="toggleSpin()"
                                class="rounded-xl border border-white/20 bg-stone-900/80 px-3 py-1.5 text-xs font-semibold text-white">
                                <span x-text="spin ? 'توقف چرخش' : 'چرخش'"></span>
                            </button>
                            <button type="button" @click="recentre()"
                                class="rounded-xl border border-white/20 bg-stone-900/80 px-3 py-1.5 text-xs font-semibold text-white">
                                قاب اول
                            </button>
                        </div>
                    </div>

                    <a x-show="active !== 'model' && imageUrl()" x-cloak
                        :href="imageUrl()" target="_blank" rel="noopener" class="block">
                        <img :src="imageUrl()" :alt="labels[active] || 'خروجی موتور سرور'"
                            class="mx-auto max-h-[44rem] w-full object-contain">
                    </a>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-if="model">
                        <button type="button" @click="active = 'model'"
                            :class="active === 'model' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                            class="rounded-xl border px-3 py-2 text-xs font-semibold transition">
                            مدل سه‌بعدی
                        </button>
                    </template>
                    <template x-for="[key, url] in Object.entries(images)" :key="key">
                        <button type="button" @click="active = key"
                            :class="active === key ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                            class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                            x-text="labels[key] || key"></button>
                    </template>
                    <template x-if="sheet">
                        <button type="button" @click="active = 'sheet'"
                            :class="active === 'sheet' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                            class="rounded-xl border px-3 py-2 text-xs font-semibold transition">
                            برگهٔ پنج‌نما
                        </button>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-500">
                    <template x-if="model">
                        <a :href="model" class="font-semibold text-brand-700 underline" download>
                            دریافت فایل GLB
                        </a>
                    </template>
                    <span>خروجی فعلی مستقیماً از قطعه‌ها و درزهای همین الگو ساخته شده است.</span>
                </div>
            </div>
        </template>

        <template x-if="status === 'pending' && Object.keys(images).length === 0 && ! model && ! sheet">
            <div class="flex min-h-64 items-center justify-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-8 text-brand-900">
                <x-icon name="refresh" class="h-6 w-6 shrink-0 animate-spin text-brand-600" />
                <div>
                    <p class="font-semibold">رندر واقعی روی سرور در حال ساخت است.</p>
                    <p class="mt-1 text-xs leading-6">این صفحه خودکار نتیجهٔ تصاویر و مدل سه‌بعدی را دریافت می‌کند.</p>
                </div>
            </div>
        </template>

        <template x-if="status === 'failed'">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-6 text-sm text-amber-900">
                موتور سرور نتوانست این خروجی را کامل کند. گزارش خطا ثبت شده است.
            </div>
        </template>

        <template x-if="status === 'none'">
            <div class="rounded-2xl border border-stone-200 bg-stone-50 px-5 py-6 text-sm text-stone-600">
                هنوز رندری ساخته نشده است؛ دکمهٔ «شبیه‌سازی کن» را بزنید تا خروجی سرور تولید شود.
            </div>
        </template>
    </div>
</x-card>
