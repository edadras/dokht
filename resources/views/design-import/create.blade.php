@php
    use App\Support\Format;
    use App\Support\Jalali;

    $candidates = $proposal['candidates'] ?? [];
    $attributeLabels = ['silhouette' => 'سیلوئت', 'length' => 'بلندی', 'sleeve' => 'آستین', 'neckline' => 'یقه'];
@endphp

<x-app-layout title="از عکس یا طرح">
    <x-page-header title="از عکس یا طرح" :back="route('patterns.index')"
        subtitle="عکس لباس را بدهید یا خودتان طرحش را بکشید؛ سامانه شکل را اندازه می‌گیرد و یک نقطه شروع پیشنهاد می‌دهد." />

    <div x-data="sketchPad(@js($config))" class="space-y-6">
        <x-alert type="info" title="این تشخیص چطور کار می‌کند؟">
            هیچ سرویس هوش مصنوعی بیرونی و هیچ ارتباط اینترنتی در کار نیست. سامانه لباس را از زمینه جدا می‌کند،
            پهنای آن را در هر ۵٪ قد می‌سنجد و از روی همین نسبت‌ها حدس می‌زند. نتیجه یک
            <span class="font-bold">نقطه شروع</span> است، نه الگوی نهایی؛ برای هر حدس دلیلش نوشته می‌شود تا
            خودتان قضاوت کنید.
        </x-alert>

        <template x-if="message">
            <x-alert type="warning"><span x-text="message"></span></x-alert>
        </template>

        {{-- دو زبانه ورودی --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="tab = 'photo'"
                :class="tab === 'photo' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                class="inline-flex items-center gap-2 rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                <x-icon name="eye" class="h-4 w-4" /> از روی عکس
            </button>
            <button type="button" @click="tab = 'sketch'; $nextTick(() => resizeCanvas())"
                :class="tab === 'sketch' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                class="inline-flex items-center gap-2 rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                <x-icon name="edit" class="h-4 w-4" /> طرح دستی
            </button>
        </div>

        {{-- ورودی یک: عکس لباس --}}
        <div x-show="tab === 'photo'" x-cloak>
            <form method="POST" action="{{ route('design-import.photo') }}" enctype="multipart/form-data"
                x-ref="photoForm">
                @csrf

                <x-card title="عکس لباس" icon="eye"
                    subtitle="بهترین نتیجه با عکس صاف و روبه‌رو، روی زمینه یک‌دست و بدون سایه به دست می‌آید.">
                    <div class="space-y-4">
                        <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                            @drop.prevent="dropPhoto($event)"
                            :class="dragging ? 'border-brand-400 bg-brand-50' : 'border-stone-300 bg-stone-50'"
                            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-12 text-center transition">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-stone-400">
                                <x-icon name="download" class="h-7 w-7" />
                            </span>

                            <p class="mt-4 font-bold text-stone-800">عکس را اینجا رها کنید</p>
                            <p class="mt-1 text-sm text-stone-500">
                                jpg، png یا webp — تا {{ Jalali::digits((int) round($maxImageKb / 1024)) }} مگابایت
                            </p>

                            <input type="file" name="photo" x-ref="photoInput" accept="image/jpeg,image/png,image/webp"
                                class="sr-only" @change="pickPhoto($event)">

                            <x-button type="button" variant="secondary" size="sm" icon="plus" class="mt-4"
                                @click="$refs.photoInput.click()">
                                انتخاب عکس
                            </x-button>

                            <p x-show="photoName" x-cloak class="mt-3 text-xs font-medium text-stone-600"
                                x-text="uploading ? photoName + ' — در حال اندازه‌گیری…' : photoName"></p>

                            @error('photo')
                                <p class="mt-3 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-advanced-section title="تنظیم جداسازی از زمینه"
                            description="اگر مرز لباس درست در نیامد، حساسیت را کم و زیاد کنید و دوباره عکس را بفرستید.">
                            <x-field label="حساسیت جداسازی" name="sensitivity"
                                hint="عدد بزرگ‌تر یعنی آستانه سخت‌گیرانه‌تر نباشد و نقطه‌های بیشتری «لباس» شمرده شود؛ وقتی رنگ زمینه به رنگ لباس نزدیک است به کار می‌آید.">
                                <x-input type="number" name="sensitivity" step="0.05" min="0.5" max="1.6"
                                    :value="old('sensitivity', '1')" />
                            </x-field>
                        </x-advanced-section>
                    </div>
                </x-card>
            </form>
        </div>

        {{-- ورودی دو: طرح دستی --}}
        <div x-show="tab === 'sketch'" x-cloak>
            <form method="POST" action="{{ route('design-import.sketch') }}" @submit="submitSketch($event)">
                @csrf
                <input type="hidden" name="strokes" x-ref="strokes">

                <x-card title="طرح دستی" icon="edit"
                    subtitle="دور لباس را با یک خط بسته بکشید؛ همان نقطه‌های قلم شما اندازه‌گیری می‌شود، نه عکس بوم.">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="tool = 'pen'"
                                :class="tool === 'pen' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                                class="inline-flex items-center gap-1.5 rounded-xl border-2 px-3 py-1.5 text-xs font-semibold transition">
                                <x-icon name="edit" class="h-4 w-4" /> قلم
                            </button>
                            <button type="button" @click="tool = 'eraser'"
                                :class="tool === 'eraser' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                                class="inline-flex items-center gap-1.5 rounded-xl border-2 px-3 py-1.5 text-xs font-semibold transition">
                                <x-icon name="trash" class="h-4 w-4" /> پاک‌کن
                            </button>
                            <x-button type="button" variant="ghost" size="sm" icon="refresh" @click="undo()">برگشت</x-button>
                            <x-button type="button" variant="ghost" size="sm" icon="x" @click="clear()">پاک‌کردن همه</x-button>

                            <span class="ms-auto text-xs text-stone-500">
                                <span x-text="pointCount"></span> نقطه کشیده شده
                            </span>
                        </div>

                        <div class="overflow-hidden rounded-2xl border-2 border-stone-200 bg-white">
                            <canvas x-ref="canvas" class="block h-[26rem] w-full touch-none"
                                @pointerdown="start($event)" @pointermove="move($event)" @pointerup="end()"
                                @pointercancel="end()" @pointerleave="end()"></canvas>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-button type="submit" icon="sparkles">این طرح را بخوان</x-button>
                            <p class="text-xs text-stone-500">
                                خط را ببندید (نقطه پایان را نزدیک نقطه شروع بیاورید) تا شکل پر شود.
                            </p>
                        </div>

                        @error('strokes', 'sketch')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </x-card>
            </form>
        </div>

        @if (! $proposal)
            <x-empty-state icon="palette" title="هنوز طرحی خوانده نشده"
                description="یک عکس بدهید یا در زبانه «طرح دستی» شکل لباس را بکشید تا اندازه‌گیری شروع شود." />
        @else
            <div class="grid gap-6 lg:grid-cols-5">
                {{-- رونمای سیلوئت: کاربر باید ببیند چه چیزی اندازه گرفته شده --}}
                <x-card title="چه چیزی اندازه گرفته شد" icon="grid" class="lg:col-span-2"
                    subtitle="خط‌های سبز جایی است که پهنا خوانده شده؛ کادر خاکستری کل شکل است.">
                    {{-- عکس و رونما هر دو در یک کادر با نسبت یکسان جا می‌شوند، پس دقیقاً روی هم می‌افتند --}}
                    <div class="relative h-96 overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
                        @if ($proposal['image_url'])
                            <img src="{{ $proposal['image_url'] }}" alt="عکس واردشده"
                                class="absolute inset-0 h-full w-full object-contain opacity-70">
                        @endif

                        <div class="absolute inset-0 p-2">{!! $proposal['overlay_svg'] !!}</div>
                    </div>

                    <p class="mt-3 text-xs leading-6 text-stone-500">
                        اگر مرز نارنجی روی لباس ننشسته، تشخیص هم اشتباه است. در آن حالت حساسیت جداسازی را
                        عوض کنید یا نوع لباس را دستی انتخاب کنید.
                    </p>
                </x-card>

                {{-- تشخیص --}}
                <x-card title="تشخیص" icon="sparkles" class="lg:col-span-3">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-2xl font-black text-stone-900">{{ $proposal['garment']['name'] }}</span>
                            <x-badge color="brand" icon="sparkles">پیشنهاد سامانه</x-badge>
                        </div>

                        <x-meter label="اطمینان تشخیص" :value="$proposal['confidence'] * 100" :max="100" />

                        @if ($proposal['confidence'] < 0.45)
                            <x-alert type="warning" title="اطمینان این تشخیص پایین است">
                                شکل ورودی نشانه کافی نداشت. بهتر است نوع لباس را خودتان از گزینه‌های زیر یا از
                                بخش «تنظیمات حرفه‌ای» انتخاب کنید.
                            </x-alert>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($attributeLabels as $key => $label)
                                @php $attribute = $proposal['attributes'][$key]; @endphp
                                <div class="rounded-xl border border-stone-200 bg-stone-50 p-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-stone-500">{{ $label }}</span>
                                        <span class="text-xs font-semibold text-stone-700">
                                            {{ Format::ratio($attribute['confidence']) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 font-bold text-stone-800">{{ $attribute['label'] }}</p>
                                    <p class="mt-1 text-xs leading-5 text-stone-500">{{ $attribute['reason'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- دلیل هر حدس --}}
            <x-card title="چرا این تشخیص؟" icon="info" subtitle="هر جمله یک اندازه‌گیری واقعی روی همان شکل است.">
                <ul class="space-y-2">
                    @foreach ($proposal['evidence'] as $item)
                        <li class="flex items-start gap-2 text-sm leading-6 text-stone-700">
                            <x-icon name="check" class="mt-1 h-4 w-4 shrink-0 text-brand-500" />
                            <span>{{ $item['text'] }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($proposal['warnings'] !== [])
                    <div class="mt-4 space-y-2">
                        @foreach ($proposal['warnings'] as $warning)
                            <x-alert type="warning">{{ $warning }}</x-alert>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- پذیرش پیشنهاد و ساخت الگو --}}
            <form method="POST" action="{{ route('design-import.apply') }}"
                x-data="{ choice: 0, candidates: @js(collect($candidates)->map(fn ($c) => [
                    'id' => $c['id'],
                    'name' => $c['name'],
                    'confidence' => $c['confidence'],
                    'template_id' => $c['template']['id'] ?? null,
                ])->all()), source: @js($proposal['source']),
                    patternName: @js($proposal['garment']['name']) }" class="space-y-6">
                @csrf

                <input type="hidden" name="source" :value="source">
                <input type="hidden" name="confidence" value="{{ $proposal['confidence'] }}">
                <input type="hidden" name="detected" :value="candidates[choice].name">

                <x-card title="نوع لباس را تأیید کنید" icon="shirt"
                    subtitle="اگر تشخیص درست نیست، یکی از گزینه‌های نزدیک را بزنید؛ الگوی پایه و پارامترها هم با آن عوض می‌شود.">
                    <div class="space-y-5">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($candidates as $index => $candidate)
                                <button type="button"
                                    @click="choice = {{ $index }}; patternName = candidates[{{ $index }}].name"
                                    :class="choice === {{ $index }} ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 bg-white text-stone-600'"
                                    class="rounded-xl border-2 px-3 py-2 text-xs font-semibold transition"
                                    title="{{ $candidate['reason'] }}">
                                    {{ $candidate['name'] }}
                                    <span class="opacity-60">({{ Format::ratio($candidate['confidence']) }})</span>
                                </button>
                            @endforeach
                        </div>

                        @foreach ($candidates as $index => $candidate)
                            <div x-show="choice === {{ $index }}" @if ($index > 0) x-cloak @endif class="space-y-3">
                                <x-meter :label="'اطمینان: '.$candidate['name']" :value="$candidate['confidence'] * 100"
                                    :max="100" />

                                <p class="text-xs leading-6 text-stone-500">{{ $candidate['reason'] }}</p>

                                <div class="rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm">
                                    <p class="font-bold text-stone-800">الگوی پایه</p>
                                    @if ($candidate['template'])
                                        <p class="mt-1 text-stone-600">{{ $candidate['template']['name'] }}</p>
                                        <p class="mt-1 text-xs leading-6 text-stone-500">{{ $candidate['template']['reason'] }}</p>
                                    @else
                                        <p class="mt-1 text-stone-600">کتابخانه الگوهای پایه خالی است؛ بدون آن نمی‌توان الگو ساخت.</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- انتخاب کاربر؛ در بخش پیشرفته می‌شود دستی عوضش کرد --}}
                        <input type="hidden" name="garment_type_id" :value="candidates[choice].id"
                            x-ref="garmentInput">
                        <input type="hidden" name="pattern_template_id" :value="candidates[choice].template_id"
                            x-ref="templateInput">
                    </div>
                </x-card>

                <x-card title="الگو برای چه کسی است؟" icon="user"
                    subtitle="اندازه‌ها از دفترچه مشتری خوانده می‌شود یا از جدول سایز استاندارد.">
                    <div x-data="{ measurementSource: 'size' }" class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="measurementSource = 'customer'"
                                :class="measurementSource === 'customer' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                                class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                                اندازه‌های یک مشتری
                            </button>
                            <button type="button" @click="measurementSource = 'size'"
                                :class="measurementSource === 'size' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600'"
                                class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition">
                                سایز استاندارد
                            </button>
                        </div>

                        <input type="hidden" name="measurement_source" :value="measurementSource">

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div x-show="measurementSource === 'customer'" x-cloak>
                                <x-field label="مشتری" name="customer_id" hint="اندازه پیش‌فرض همان مشتری استفاده می‌شود.">
                                    <x-select name="customer_id" placeholder="انتخاب مشتری"
                                        x-bind:disabled="measurementSource !== 'customer'">
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
                                    :selected="'40'" />
                            </x-field>

                            <x-field label="نام الگو" name="name" class="sm:col-span-2"
                                hint="اگر خالی بماند، نام الگوی پایه گذاشته می‌شود.">
                                <x-input name="name" x-model="patternName"
                                    :value="$proposal['garment']['name']" placeholder="مثلاً پیراهن خانم رضایی" />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                {{-- تنظیمات حرفه‌ای --}}
                <x-advanced-section title="تنظیمات حرفه‌ای"
                    description="انتخاب دستی نوع لباس و الگوی پایه، پارامترهای مدل و عددهای خام اندازه‌گیری.">
                    <div class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="نوع لباس (انتخاب دستی)" hint="این انتخاب جای تشخیص خودکار را می‌گیرد.">
                                <x-select placeholder="همان تشخیص بالا"
                                    @change="if ($event.target.value) { $refs.garmentInput.value = $event.target.value }">
                                    @foreach ($garmentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name_fa }} — {{ $type->categoryLabel() }}</option>
                                    @endforeach
                                </x-select>
                            </x-field>

                            <x-field label="الگوی پایه" hint="ساخت الگو همیشه از یک الگوی پایه واقعی انجام می‌شود.">
                                <x-select placeholder="همان پیشنهاد بالا"
                                    @change="if ($event.target.value) { $refs.templateInput.value = $event.target.value }">
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}">
                                            {{ $template->name_fa }}
                                            @if ($template->garmentType)
                                                — {{ $template->garmentType->name_fa }}
                                            @endif
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>

                        {{-- پارامترهای هر گزینه جدا؛ فقط گزینه انتخاب‌شده فرستاده می‌شود --}}
                        @foreach ($candidates as $index => $candidate)
                            @if ($candidate['params'] !== [])
                                <div x-show="choice === {{ $index }}" @if ($index > 0) x-cloak @endif>
                                    <h3 class="mb-3 text-sm font-bold text-stone-700">
                                        پارامترهای پیشنهادی برای «{{ $candidate['template']['name'] ?? $candidate['name'] }}»
                                    </h3>

                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($candidate['params'] as $key => $value)
                                            @php $rule = $candidate['template']['schema'][$key] ?? []; @endphp

                                            <x-field :label="$rule['label'] ?? $key"
                                                :hint="$candidate['param_reasons'][$key] ?? null">
                                                @if (($rule['type'] ?? null) === 'toggle')
                                                    <x-select :name="'params['.$key.']'"
                                                        :options="[1 => 'دارد', 0 => 'ندارد']"
                                                        :selected="$value ? 1 : 0"
                                                        x-bind:disabled="choice !== {{ $index }}" />
                                                @else
                                                    <x-input type="number" :name="'params['.$key.']'" :value="$value"
                                                        :step="$rule['step'] ?? 0.5" :min="$rule['min'] ?? null"
                                                        :max="$rule['max'] ?? null"
                                                        x-bind:disabled="choice !== {{ $index }}" />
                                                @endif
                                            </x-field>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        {{-- عددهای خام --}}
                        <div>
                            <h3 class="mb-3 text-sm font-bold text-stone-700">عددهای خام اندازه‌گیری</h3>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[28rem] text-start text-xs">
                                    <tbody class="divide-y divide-stone-100">
                                        @foreach ([
                                            'نسبت قد به پهنای بالا' => $proposal['features']['length_ratio'],
                                            'نسبت پهنای لبه پایین به بالا' => $proposal['features']['hem_ratio'],
                                            'فرورفتگی کمر' => $proposal['features']['waist_pinch'],
                                            'سهم سطرهای دوشاخه (پاچه)' => $proposal['features']['split_ratio'],
                                            'برجستگی آستین' => $proposal['features']['sleeve_bump'],
                                            'گودی یقه (نسبت به قد)' => $proposal['features']['neck_depth'],
                                            'قرینگی چپ و راست' => $proposal['features']['symmetry'],
                                            'کیفیت ورودی' => $proposal['features']['quality'],
                                            'نشانه‌داری شکل' => $proposal['features']['distinctiveness'],
                                        ] as $label => $value)
                                            <tr>
                                                <td class="py-1.5 text-stone-500">{{ $label }}</td>
                                                <td dir="ltr" class="py-1.5 text-end font-semibold text-stone-800">
                                                    {{ Jalali::digits(number_format((float) $value, 2)) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <p class="mt-2 text-xs leading-6 text-stone-500">
                                همه این عددها نسبی‌اند: در عکس هیچ خط‌کشی نیست، پس هیچ اندازه سانتی‌متری از عکس
                                در نمی‌آید. اندازه‌های واقعی الگو از مشتری یا از جدول سایز می‌آید.
                            </p>
                        </div>
                    </div>
                </x-advanced-section>

                <div class="flex flex-wrap items-center gap-3">
                    <x-button type="submit" size="lg" icon="scissors">بساز الگو</x-button>

                    <p class="text-xs text-stone-500">
                        الگو با همان تولیدکننده‌های واقعی سامانه ساخته می‌شود، پس ویرایش‌پذیر، سایزبندی‌پذیر و
                        خروجی‌گرفتنی است.
                    </p>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
