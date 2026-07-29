<x-app-layout :title="'پرو — '.$project->name">
    @php
        $digits = fn ($value) => \App\Support\Jalali::digits((string) $value);
        $suggestedByKey = collect($suggested)->keyBy('key');
    @endphp

    <x-page-header title="پرو روی تن مشتری"
        :subtitle="$project->name.($project->customer ? ' • '.$project->customer->name : '')"
        :back="route('projects.show', $project)" />

    @if (session('error'))
        <x-alert type="warning">{{ session('error') }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="ثبت پروی تازه" icon="ruler"
                subtitle="هرچه دیدید بنویسید؛ عدد مثبت یعنی «بیشترش کن» و منفی یعنی «کمترش کن».">
                @if (! $project->pattern)
                    <p class="text-sm text-stone-500">این پروژه هنوز الگو ندارد؛ اول الگو را بسازید.</p>
                @else
                    <form method="POST" action="{{ route('projects.fittings.store', $project) }}" class="space-y-4">
                        @csrf

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="تاریخ پرو" name="fitted_on" hint="خالی بگذارید یعنی امروز">
                                <x-input name="fitted_on" :value="old('fitted_on', $today)" dir="ltr" />
                            </x-field>
                            <x-field label="یادداشت" name="notes">
                                <x-input name="notes" :value="old('notes')" placeholder="مثلاً: مشتری آستین را کوتاه‌تر می‌خواهد" />
                            </x-field>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($catalogue as $key => $row)
                                @php $hint = $suggestedByKey[$key] ?? null; @endphp
                                <div @class([
                                    'rounded-2xl border p-3',
                                    'border-brand-200 bg-brand-50/40' => $hint !== null,
                                    'border-stone-200' => $hint === null,
                                ])>
                                    <label class="block text-sm font-medium text-stone-700" for="values-{{ $key }}">
                                        {{ $row['label'] }}
                                    </label>
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $row['symptom'] }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        {{-- گام یک‌دهم: پیشنهاد تست تناسب عددهایی مثل ۱٫۴ می‌دهد و با گام نیم،
                                             مرورگر ارسال فرم را بی‌صدا رد می‌کرد --}}
                                        <input id="values-{{ $key }}" type="number" step="0.1"
                                            min="{{ $row['min'] }}" max="{{ $row['max'] }}"
                                            name="values[{{ $key }}]"
                                            value="{{ old('values.'.$key, $hint['value'] ?? '') }}"
                                            dir="ltr"
                                            class="w-24 rounded-xl border-stone-200 text-sm focus:border-brand-400 focus:ring-brand-300" />
                                        <span class="text-xs text-stone-500">سانتی‌متر</span>
                                        @if ($hint)
                                            <span class="ms-auto rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-700">
                                                پیشنهاد تست تناسب
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <x-button type="submit" icon="check">ثبت پرو</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="نوبت‌های پرو" icon="clock"
                :subtitle="$digits($fittings->count()).' نوبت ثبت شده'">
                @if ($fittings->isEmpty())
                    <p class="text-sm text-stone-500">هنوز پرویی ثبت نشده است.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($fittings as $fitting)
                            <li class="rounded-2xl border border-stone-200 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-stone-700">
                                        پروی {{ $digits($fitting->round) }}ام
                                    </span>
                                    <span class="text-xs text-stone-500">{{ $fitting->fittedOnJalali() }}</span>
                                    @if ($fitting->isApplied())
                                        <span class="ms-auto rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">
                                            روی الگو نشست
                                        </span>
                                    @else
                                        <span class="ms-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                                            اعمال نشده
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 text-sm text-stone-600">{{ $fitting->summary() }}</p>

                                @if ($fitting->notes)
                                    <p class="mt-1 text-xs text-stone-500">{{ $fitting->notes }}</p>
                                @endif

                                @if (! $fitting->isApplied())
                                    <div class="mt-3 flex gap-2">
                                        <form method="POST" action="{{ route('projects.fittings.apply', [$project, $fitting]) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" icon="refresh">اعمال روی الگو</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('projects.fittings.destroy', [$project, $fitting]) }}"
                                            onsubmit="return confirm('این پرو پاک شود؟')">
                                            @csrf @method('DELETE')
                                            <x-button type="submit" size="sm" variant="ghost" icon="trash">پاک کردن</x-button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="این حلقه چطور کار می‌کند" icon="info">
                <ol class="space-y-2 text-sm text-stone-600">
                    <li>۱. لباس نیمه‌دوخته را روی تن مشتری امتحان می‌کنید.</li>
                    <li>۲. آنچه دیدید را با عدد ثبت می‌کنید.</li>
                    <li>۳. با «اعمال روی الگو»، اندازه‌ها و آزادی و پارامترهای درفت اصلاح می‌شوند و الگو از نو ساخته می‌شود.</li>
                    <li>۴. پیش از هر اصلاح یک نسخه ثبت می‌شود، پس هر پرو برگشت‌پذیر است.</li>
                </ol>
            </x-card>
        </div>
    </div>
</x-app-layout>
