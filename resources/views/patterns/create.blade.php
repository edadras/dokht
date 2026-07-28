<x-app-layout title="الگوی جدید">
    <x-page-header title="الگوی جدید" subtitle="مدل را انتخاب کنید و بگویید برای چه کسی است؛ بقیه کارها خودکار انجام می‌شود."
        :back="route('patterns.index')" />

    <form method="POST" action="{{ route('patterns.store') }}"
        x-data="{ template: @js($selectedTemplate), source: @js(old('customer_id') ? 'customer' : 'size') }" class="space-y-6">
        @csrf

        {{-- گام یک: انتخاب مدل از کتابخانه --}}
        <x-card title="۱. مدل الگو را انتخاب کنید" icon="book"
            subtitle="مدل‌های پایه آماده‌اند؛ اندازه‌ها روی همان مدل پیاده می‌شود.">
            @if ($templates->isEmpty())
                <x-alert type="warning">
                    هنوز الگوی پایه‌ای در کتابخانه نیست. از مدیر سامانه بخواهید کتابخانه الگوها را پر کند.
                </x-alert>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <label class="cursor-pointer">
                        <input type="radio" name="pattern_template_id" value="{{ $template->id }}" class="peer sr-only"
                            x-model.number="template" @checked(old('pattern_template_id') == $template->id) required>

                        <div class="h-full rounded-2xl border-2 border-stone-200 bg-white p-3 transition peer-checked:border-brand-500 peer-checked:ring-2 peer-checked:ring-brand-100 hover:border-brand-300">
                            <div class="flex h-32 items-center justify-center overflow-hidden rounded-xl bg-stone-50 [&>svg]:h-full [&>svg]:w-auto">
                                {!! $template->preview_svg ?: '' !!}
                            </div>

                            <p class="mt-3 font-bold text-stone-900">{{ $template->name_fa }}</p>

                            @if ($template->garmentType)
                                <p class="text-xs text-brand-600">{{ $template->garmentType->name_fa }}</p>
                            @endif

                            <p class="mt-1 text-xs leading-5 text-stone-500">{{ $template->description }}</p>
                        </div>
                    </label>
                @endforeach
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

                <div class="space-y-5">
                    <h3 class="text-sm font-bold text-stone-700">پارامترهای مدل انتخاب‌شده</h3>

                    @foreach ($templates as $template)
                        <div x-show="template === {{ $template->id }}" x-cloak class="space-y-4">
                            <p class="text-xs text-stone-500">{{ $template->name_fa }}</p>

                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($template->params_schema ?? [] as $key => $field)
                                    @php
                                        $value = data_get($template->default_params, $key);
                                        $isToggle = ($field['type'] ?? 'number') === 'toggle';
                                    @endphp

                                    <x-field :label="$field['label'] ?? $key" :hint="$field['hint'] ?? null">
                                        @if ($isToggle)
                                            <select name="params[{{ $key }}]"
                                                x-bind:disabled="template !== {{ $template->id }}"
                                                class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm shadow-sm">
                                                <option value="1" @selected($value)>دارد</option>
                                                <option value="0" @selected(! $value)>ندارد</option>
                                            </select>
                                        @else
                                            <x-input type="number" :name="'params['.$key.']'" :value="$value"
                                                :step="$field['step'] ?? 0.5" :min="$field['min'] ?? null"
                                                :max="$field['max'] ?? null"
                                                x-bind:disabled="template !== {{ $template->id }}"
                                                :suffix="$field['unit'] ?? null" />
                                        @endif
                                    </x-field>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

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
