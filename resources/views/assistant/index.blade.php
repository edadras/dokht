<x-app-layout title="دستیار">
    <x-page-header title="دستیار کارگاه"
        subtitle="پرسش خود را بنویسید؛ پاسخ از شناسنامه پارچه، قواعد طراحی و آخرین شبیه‌سازی پروژه ساخته می‌شود." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="پرسش شما" icon="sparkles">
                <form method="POST" action="{{ route('assistant.ask') }}" class="space-y-4" x-data>
                    @csrf

                    <x-field label="پرسش" name="question" required>
                        <x-textarea name="question" :value="$selected['question']" rows="2"
                            placeholder="مثلاً: این پارچه برای مانتو مناسب است؟" x-ref="question" />
                    </x-field>

                    <div>
                        <p class="mb-2 text-xs font-medium text-stone-500">پرسش‌های نمونه — روی هر کدام بزنید:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($examples as $example)
                                <button type="button" @click="$refs.question.value = @js($example)"
                                    class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-600 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">
                                    {{ $example }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="پارچه" name="fabric_id">
                            <x-select name="fabric_id" :options="$fabrics->mapWithKeys(fn ($fabric) => [$fabric->id => $fabric->displayName()])->all()"
                                :selected="$selected['fabric_id']" placeholder="انتخاب نشده" />
                        </x-field>

                        <x-field label="مدل لباس" name="garment_type_id">
                            <x-select name="garment_type_id" :options="$garmentTypes" :selected="$selected['garment_type_id']"
                                placeholder="انتخاب نشده" />
                        </x-field>

                        <x-field label="پروژه" name="project_id" hint="اگر پروژه بدهید، پارچه و مدلش خودکار خوانده می‌شود.">
                            <x-select name="project_id" :options="$projects->mapWithKeys(fn ($project) => [$project->id => $project->name])->all()"
                                :selected="$selected['project_id']" placeholder="انتخاب نشده" />
                        </x-field>
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" icon="sparkles">بپرس</x-button>
                    </div>
                </form>
            </x-card>

            @if ($answer)
                @php($fromModel = ($answer['source'] ?? 'rules') === 'claude')
                <x-card :title="'پاسخ — '.$answer['topic_label']" icon="check-circle">
                    <div @class([
                        'mb-3 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium',
                        'bg-violet-50 text-violet-800' => $fromModel,
                        'bg-stone-100 text-stone-600' => ! $fromModel,
                    ])>
                        <x-icon :name="$fromModel ? 'sparkles' : 'check-circle'" class="h-4 w-4 shrink-0" />
                        <span>{{ $answer['source_label'] ?? 'این پاسخ از قواعد سامانه آمده است' }}</span>
                    </div>

                    @if (! empty($answer['fallback_reason']))
                        <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-6 text-amber-800">
                            {{ $answer['fallback_reason'] }} پاسخ زیر با قواعد خود سامانه ساخته شده است.
                        </p>
                    @endif

                    <p class="mb-4 rounded-xl bg-brand-50 p-4 text-sm font-semibold leading-7 text-brand-900">
                        {{ $answer['headline'] }}
                    </p>

                    @if (! empty($answer['points']))
                        <ul class="space-y-2">
                            @foreach ($answer['points'] as $point)
                                <li class="flex gap-2 text-sm leading-7 text-stone-700">
                                    <x-icon name="check" class="mt-1.5 h-4 w-4 shrink-0 text-emerald-500" />
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-advanced-section title="این پاسخ از کجا آمد؟"
                    description="فهرست قاعده‌ها و معیارهایی که پاسخ بالا را ساختند." :open="true">
                    @if (empty($answer['reasons']))
                        <p class="text-sm text-stone-500">این پاسخ از پیش‌فرض‌های عمومی سامانه آمده است.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($answer['reasons'] as $reason)
                                <li class="border-s-2 border-brand-200 ps-3">
                                    <p class="text-sm font-semibold text-stone-800">{{ $reason['label'] }}</p>
                                    <p class="text-sm leading-6 text-stone-600">{{ $reason['text'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-advanced-section>
            @else
                <x-empty-state icon="sparkles" title="هنوز پرسشی نپرسیده‌اید"
                    description="یکی از پرسش‌های نمونه را انتخاب کنید یا پرسش خودتان را بنویسید. دستیار همیشه می‌گوید پاسخ را از کجا آورده است." />
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="دستیار چه چیزهایی می‌داند؟" icon="info">
                <ul class="space-y-2 text-sm text-stone-600">
                    <li class="flex gap-2">
                        <x-icon name="layers" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                        شناسنامه فیزیکی همه پارچه‌های شما و بانک پارچه
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                        قواعد طراحی سراسری و قواعد خود کارگاه
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="cube" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                        نتیجه آخرین شبیه‌سازی و تست تناسب پروژه
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="shirt" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                        سلیقه پارچه هر مدل لباس
                    </li>
                </ul>

                @if (($driver ?? 'rules') === 'claude')
                    <p class="mt-4 text-xs leading-6 text-stone-500">
                        در تنظیمات این سامانه، کمک‌گرفتن از مدل زبانی روشن است: همین داده‌ها برای مدل فرستاده می‌شود تا
                        پاسخ روان‌تری بنویسد. نام و شماره تماس مشتری هرگز فرستاده نمی‌شود و اگر سرویس در دسترس نباشد،
                        پاسخ از قواعد خود سامانه می‌آید. زیر هر پاسخ نوشته شده که از کدام راه آمده است.
                    </p>
                @else
                    <p class="mt-4 text-xs leading-6 text-stone-500">
                        دستیار به هیچ سرویس بیرونی وصل نیست؛ پاسخ‌ها فقط از داده‌های همین سامانه ساخته می‌شود.
                    </p>
                @endif
            </x-card>

            <x-card title="جای دیگری هم بگردید" icon="book">
                <div class="space-y-2">
                    <x-button href="{{ route('guides.index') }}" variant="soft" size="sm" icon="book" class="w-full">
                        بخش آموزش
                    </x-button>
                    <x-button href="{{ route('fabric-bank.index') }}" variant="soft" size="sm" icon="palette"
                        class="w-full">بانک پارچه</x-button>
                    <x-button href="{{ route('rules.index') }}" variant="soft" size="sm" icon="check-circle"
                        class="w-full">قواعد طراحی</x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
