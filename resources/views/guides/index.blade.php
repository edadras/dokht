@php
    use App\Support\Format;
@endphp

<x-app-layout title="آموزش">
    <x-page-header title="آموزش"
        :subtitle="'راهنمای برش و دوخت هر پارچه به زبان ساده — '.Format::number($total).' درس.'" />

    <form method="GET" action="{{ route('guides.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="جست‌وجو" name="q" class="min-w-56 flex-1">
            <x-input name="q" :value="$q" placeholder="مثلاً حریر، لایی، اندازه‌گیری…" />
        </x-field>

        <x-field label="دسته" name="scope" class="min-w-44">
            <x-select name="scope" :options="$scopes" :selected="$scope" placeholder="همه" />
        </x-field>

        <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
    </form>

    @if ($grouped->isEmpty())
        <x-empty-state icon="book" title="راهنمایی پیدا نشد"
            description="عبارت دیگری را جست‌وجو کنید یا همه دسته‌ها را ببینید.">
            <x-slot:action>
                <x-button href="{{ route('guides.index') }}" variant="secondary" icon="refresh">نمایش همه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-6">
            @foreach ($scopes as $scopeKey => $scopeLabel)
                @continue(! $grouped->has($scopeKey))

                <x-card :title="$scopeLabel" :subtitle="Format::number($grouped[$scopeKey]->count()).' درس'" icon="book">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($grouped[$scopeKey] as $guide)
                            <a href="{{ route('guides.show', $guide) }}"
                                class="flex gap-3 rounded-xl border border-stone-200 p-3 transition hover:border-brand-300 hover:bg-stone-50">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                                    <x-icon name="book" class="h-5 w-5" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-stone-800">{{ $guide->title }}</span>
                                    @if ($guide->summary)
                                        <span class="mt-0.5 block text-xs leading-5 text-stone-500">
                                            {{ $guide->summary }}
                                        </span>
                                    @endif
                                    @if ($guide->fabricType)
                                        <span class="mt-1.5 inline-flex">
                                            <x-badge color="clay">{{ $guide->fabricType->name_fa }}</x-badge>
                                        </span>
                                    @elseif ($guide->garmentType)
                                        <span class="mt-1.5 inline-flex">
                                            <x-badge color="violet">{{ $guide->garmentType->name_fa }}</x-badge>
                                        </span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>
