{{--
    گام دوم: سبک‌ها.

    هر گروه سبک یک ردیف بازشو است و هر سبک یک کارت انتخابی. سبکی که روی پایه فعلی
    نمی‌نشیند خاموش نشان داده می‌شود و دلیلش (به فارسی، از خودِ سبک) زیرش می‌آید —
    هیچ سبکی پنهان نمی‌شود.
--}}
<x-card title="۲. سبک‌ها" icon="sparkles"
    subtitle="هر چند سبک که خواستید؛ به ترتیب درست روی لباس اجرا می‌شوند: خط یقه، یقه، آستین، چین، لبه، جیب، بست و جزئیات.">
    <x-slot:actions>
        <span class="text-xs text-stone-500">
            <span x-text="digits(styles.length)"></span> سبک انتخاب شده
        </span>
    </x-slot:actions>

    <div class="space-y-4">
        <div x-show="styles.length" x-cloak class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-stone-500">ترتیب اجرا:</span>
            <template x-for="(style, index) in ordered()" :key="style.key">
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                    <span x-text="digits(index + 1) + '. ' + styleLabel(style.key)"></span>
                    {{-- سمت: برای لباس نامتقارن مثل جیب فقط سمت چپ یا یقه یک‌طرفه --}}
                    <select x-model="style.side" @change="schedule()"
                        class="rounded-full border-0 bg-transparent py-0 ps-1 pe-4 text-xs text-brand-700 focus:ring-0"
                        :aria-label="'سمت ' + styleLabel(style.key)">
                        <option value="both">دو طرف</option>
                        <option value="right">فقط راست</option>
                        <option value="left">فقط چپ</option>
                    </select>
                    <button type="button" @click="toggleStyle(style.key)" class="text-brand-400 transition hover:text-rose-600"
                        :aria-label="'برداشتن ' + styleLabel(style.key)">×</button>
                </span>
            </template>
        </div>

        {{-- ورودی‌های پنهانی که فهرست سبک‌ها را با فرم می‌فرستند --}}
        <template x-for="(style, index) in styles" :key="'key-' + style.key">
            <span>
                <input type="hidden" :name="'styles[' + index + '][key]'" :value="style.key">
                <input type="hidden" :name="'styles[' + index + '][side]'" :value="style.side || 'both'">
            </span>
        </template>

        @php
            /*
             * ترتیبِ گروه‌ها همان ترتیبی است که سبک‌ها روی لباس اجرا می‌شوند
             * (STYLE_ORDER در سرویس ترکیب): اول خط یقه، بعد یقه، بعد آستین و…
             * پیش‌تر ترتیبِ رجیستری بود و با ترتیبِ اجرای واقعی نمی‌خواند، پس
             * فهرست بی‌نظم به نظر می‌رسید.
             */
            $ordered = [];

            foreach ($catalogue['style_order'] ?? [] as $g) {
                if (isset($catalogue['styles'][$g])) {
                    $ordered[$g] = $catalogue['styles'][$g];
                }
            }

            $ordered += $catalogue['styles'];

            /*
             * دو گروه با نقش‌های گام یک هم‌نام‌اند و همین گیج‌کننده بود: آن‌جا
             * *کدام* آستین و یقه، این‌جا *چطور* دوخته شود. جمله‌ای زیر هر کدام
             * همین را می‌گوید.
             */
            $overlap = [
                'sleeve' => 'در گام یک گفتید کدام آستین؛ این‌جا شکلِ دوختش عوض می‌شود (رگلان، کیمونو، شانه‌افتاده…). اگر در گام یک «بدون آستین» زده‌اید، این‌ها اثری ندارند.',
                'collar' => 'در گام یک گفتید کدام یقهٔ دوخته‌شده؛ این‌جا جنسِ خودِ یقه عوض می‌شود.',
                'neckline' => 'این خطِ یقه است، یعنی جایی که پارچه دور گردن بریده می‌شود — نه یقه‌ای که رویش دوخته می‌شود.',
                'detail' => 'سر آستین و مچ هم این‌جاست، چون روی آستینی که انتخاب کرده‌اید می‌نشیند.',
            ];
        @endphp

        @forelse ($ordered as $group => $row)
            @php $fits = collect($row['styles'])->filter(fn ($s, $k) => ($availability[$k]['ok'] ?? true))->count(); @endphp

            <div class="rounded-2xl border border-stone-200">
                <button type="button" @click="openGroups['{{ $group }}'] = ! openGroups['{{ $group }}']"
                    class="flex w-full items-center gap-3 px-4 py-3 text-start transition hover:bg-stone-50">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-stone-800">{{ $row['label'] }}</span>
                        @if (isset($overlap[$group]))
                            <span class="mt-0.5 block text-xs leading-5 text-amber-700">{{ $overlap[$group] }}</span>
                        @endif
                        <span class="block text-xs text-stone-500">
                            {{ \App\Support\Jalali::digits((string) count($row['styles'])) }} سبک،
                            <span x-text="digits(fitCount('{{ $group }}'))">{{ \App\Support\Jalali::digits((string) $fits) }}</span>
                            تای آن روی این پایه می‌نشیند
                        </span>
                    </span>

                    <template x-for="key in chosenIn('{{ $group }}')" :key="key">
                        <span class="hidden rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 sm:inline-flex"
                            x-text="styleLabel(key)"></span>
                    </template>

                    <x-icon name="chevron-down" class="h-5 w-5 shrink-0 text-stone-400 transition"
                        x-bind:class="openGroups['{{ $group }}'] && 'rotate-180'" />
                </button>

                <div x-show="openGroups['{{ $group }}']" x-collapse x-cloak class="border-t border-stone-100 p-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($row['styles'] as $key => $style)
                            @include('patterns.partials.compose-style-card', ['key' => $key, 'style' => $style])
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state icon="sparkles" title="هنوز سبکی در کاتالوگ نیست"
                description="به‌محض افزوده‌شدن سبک‌ها همین‌جا نشان داده می‌شوند." />
        @endforelse
    </div>
</x-card>
