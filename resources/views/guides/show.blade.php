<x-app-layout :title="$guide->title">
    <x-page-header :title="$guide->title" :subtitle="$guide->summary" :back="route('guides.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card icon="book" :title="$guide->scopeLabel()">
                @if (empty($paragraphs))
                    <p class="text-sm text-stone-500">متن این راهنما هنوز نوشته نشده است.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($paragraphs as $paragraph)
                            <p class="text-sm leading-8 text-stone-700">{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if (! empty($guide->tips))
                <x-card title="اشتباه‌های رایج" subtitle="این‌ها را انجام ندهید." icon="alert">
                    <ul class="space-y-2">
                        @foreach ($guide->tips as $tip)
                            <li class="flex gap-2 text-sm text-stone-700">
                                <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                                <span>{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($guide->fabricType)
                <x-card title="پارچه این درس" icon="palette">
                    <p class="font-semibold text-stone-800">{{ $guide->fabricType->name_fa }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $guide->fabricType->compositionLabel() }}</p>

                    <x-button href="{{ route('fabric-bank.show', $guide->fabricType) }}" variant="soft" size="sm"
                        icon="eye" class="mt-3">دیدن شناسنامه پارچه</x-button>
                </x-card>
            @endif

            @if ($related->isNotEmpty())
                <x-card title="راهنماهای مرتبط" icon="book">
                    <ul class="space-y-2">
                        @foreach ($related as $item)
                            <li>
                                <a href="{{ route('guides.show', $item) }}"
                                    class="block rounded-xl px-2 py-2 text-sm font-semibold text-stone-800 transition hover:bg-stone-50">
                                    {{ $item->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card>
                <p class="text-sm text-stone-600">پرسشی درباره پارچه یا مدل خودتان دارید؟</p>
                <x-button href="{{ route('assistant.index') }}" variant="soft" size="sm" icon="sparkles" class="mt-3">
                    پرسیدن از دستیار
                </x-button>
            </x-card>
        </div>
    </div>
</x-app-layout>
