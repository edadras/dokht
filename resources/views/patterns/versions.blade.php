<x-app-layout :title="'نسخه‌های '.$pattern->name">
    @php
        $digits = fn ($value) => \App\Support\Jalali::digits((string) $value);
    @endphp

    <x-page-header title="تاریخچه نسخه‌ها" :subtitle="$pattern->name" :back="route('patterns.show', $pattern)">
        <x-slot:actions>
            <form method="POST" action="{{ route('patterns.versions.store', $pattern) }}" class="flex items-end gap-2">
                @csrf
                <x-field label="یادداشت نسخه" name="note">
                    <x-input name="note" placeholder="مثلاً قبل از تنگ کردن پهلو" />
                </x-field>
                <x-button type="submit" icon="clock">ثبت نسخه از وضعیت فعلی</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <x-card title="وضعیت فعلی" icon="eye"
        :subtitle="'نسخه '.$digits($pattern->version).' • '.$digits($current['summary']['piece_count']).' قطعه'">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat label="قطعه‌ها" :value="$digits($current['summary']['piece_count'])" icon="layers" />
            <x-stat label="تعداد برش" :value="$digits($current['summary']['cut_count'])" icon="scissors" color="clay" />
            <x-stat label="مساحت" :value="\App\Support\Format::number($current['summary']['total_area'] / 10000, 2).' متر مربع'"
                icon="grid" color="sky" />
        </div>
    </x-card>

    @if ($versions->isEmpty())
        <div class="mt-6">
            <x-empty-state icon="clock" title="نسخه‌ای ثبت نشده"
                description="هر بار که الگو را بازتولید یا در ویرایشگر ذخیره کنید، نسخه پیشین اینجا نگه داشته می‌شود." />
        </div>
    @else
        <ol class="mt-6 space-y-4">
            @foreach ($versions as $version)
                @php $diff = $comparisons[$version->id] ?? ['added' => [], 'removed' => [], 'changed' => []]; @endphp

                <li class="relative rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge color="brand">نسخه {{ $digits($version->version) }}</x-badge>
                                <span class="text-sm font-semibold text-stone-800">
                                    {{ $version->note ?: 'بدون یادداشت' }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-stone-500">
                                {{ \App\Support\Jalali::dateTime($version->created_at) }}
                                • {{ $version->author?->name ?? 'نامشخص' }}
                                • {{ $digits($version->pieceCount()) }} قطعه
                            </p>
                        </div>

                        <form method="POST" action="{{ route('patterns.versions.restore', [$pattern, $version]) }}"
                            onsubmit="return confirm('وضعیت فعلی پیش از بازگردانی ذخیره می‌شود. ادامه می‌دهید؟')">
                            @csrf
                            <x-button type="submit" variant="secondary" size="sm" icon="refresh">بازگردانی</x-button>
                        </form>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        @if ($diff['added'])
                            <p class="text-emerald-700">
                                قطعه‌های اضافه‌شده پس از این نسخه: {{ implode('، ', $diff['added']) }}
                            </p>
                        @endif

                        @if ($diff['removed'])
                            <p class="text-rose-700">
                                قطعه‌های حذف‌شده پس از این نسخه: {{ implode('، ', $diff['removed']) }}
                            </p>
                        @endif

                        @if ($diff['changed'])
                            <div class="overflow-x-auto thin-scroll">
                                <table class="w-full text-xs">
                                    <thead class="text-stone-500">
                                        <tr>
                                            <th class="p-1.5 text-start">قطعه</th>
                                            <th class="p-1.5 text-start">مساحت این نسخه</th>
                                            <th class="p-1.5 text-start">مساحت بعدی</th>
                                            <th class="p-1.5 text-start">تغییر</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100">
                                        @foreach ($diff['changed'] as $row)
                                            <tr>
                                                <td class="p-1.5">{{ $row['name'] }}</td>
                                                <td class="p-1.5">{{ $digits(round($row['from'])) }}</td>
                                                <td class="p-1.5">{{ $digits(round($row['to'])) }}</td>
                                                <td class="p-1.5 font-semibold {{ $row['delta'] > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                    {{ $row['delta'] > 0 ? '+' : '' }}{{ $digits($row['delta']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (! $diff['added'] && ! $diff['removed'] && ! $diff['changed'])
                            <p class="text-stone-400">تغییر اندازه‌داری بعد از این نسخه ثبت نشده است.</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-app-layout>
