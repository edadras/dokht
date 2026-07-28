<x-app-layout :title="$pattern->name">
    <x-page-header :title="$pattern->name"
        :subtitle="'منتشرشده توسط '.($pattern->workshop?->name ?? 'کارگاه ناشناس')"
        :back="route('library.index', ['tab' => 'published'])">
        <x-slot:actions>
            <form method="POST" action="{{ route('library.copy', $pattern->id) }}">
                @csrf
                <x-button type="submit" icon="copy">کپی به کارگاه من</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if ($isMine)
        <x-alert type="info" class="mb-6">
            این الگو خودِ کارگاه شماست. کپی گرفتن مجاز است و نسخه تازه با نام «(کپی)» ساخته می‌شود.
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="پیش‌نمایش الگو" icon="eye">
                <div class="flex min-h-64 items-center justify-center rounded-xl bg-stone-50 p-4">
                    @if ($preview)
                        <div class="w-full [&>svg]:h-auto [&>svg]:w-full">{!! $preview !!}</div>
                    @else
                        <div class="py-10 text-center">
                            <x-icon name="scissors" class="mx-auto h-10 w-10 text-stone-300" />
                            <p class="mt-2 text-sm text-stone-500">پیش‌نمایش این الگو در دسترس نیست.</p>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card title="قطعه‌های الگو" icon="layers" padding="p-0" class="mt-6">
                @if ($pattern->pieces->isEmpty())
                    <p class="p-5 text-sm text-stone-500">این الگو قطعه‌ای ثبت‌شده ندارد.</p>
                @else
                    <div class="overflow-x-auto thin-scroll">
                        <table class="w-full text-sm">
                            <thead class="bg-stone-50 text-xs text-stone-500">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium">قطعه</th>
                                    <th class="px-4 py-3 text-start font-medium">لایه</th>
                                    <th class="px-4 py-3 text-start font-medium">تعداد برش</th>
                                    <th class="px-4 py-3 text-start font-medium">اندازه (عرض × قد)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($pattern->pieces as $piece)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-stone-800">{{ $piece->name }}</span>
                                            @if ($piece->on_fold)
                                                <x-badge color="sky" class="ms-2">روی دوتا</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-stone-600">{{ $piece->layerLabel() }}</td>
                                        <td class="px-4 py-3 text-stone-600">
                                            {{ \App\Support\Jalali::digits($piece->cut_quantity) }}
                                        </td>
                                        <td class="px-4 py-3 text-stone-600">
                                            {{ \App\Support\Format::cm($piece->width()) }}
                                            ×
                                            {{ \App\Support\Format::cm($piece->height()) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="شناسنامه الگو" icon="info">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">نوع لباس</dt>
                        <dd class="font-medium">{{ $pattern->garmentType?->name_fa ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">سایز پایه</dt>
                        <dd class="font-medium">{{ \App\Support\Jalali::digits($pattern->base_size) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">شمار قطعه</dt>
                        <dd class="font-medium">{{ \App\Support\Jalali::digits($pattern->pieces->count()) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">کپی‌گرفته‌شده</dt>
                        <dd class="font-medium">{{ \App\Support\Jalali::digits($pattern->copies_count) }} بار</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">کارگاه منتشرکننده</dt>
                        <dd class="font-medium">{{ $pattern->workshop?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="آزادی (اِیز)" icon="ruler">
                @php $ease = $pattern->ease ?? []; @endphp

                @if (empty($ease))
                    <p class="text-sm text-stone-500">آزادی جداگانه‌ای ثبت نشده است.</p>
                @else
                    <dl class="space-y-2 text-sm">
                        @foreach ($ease as $key => $value)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-stone-500">{{ \App\Support\Measurements::label($key) }}</dt>
                                <dd class="font-medium">{{ \App\Support\Format::cm($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-card>

            <x-advanced-section title="جای دوخت این الگو" description="اندازه لبه‌ها؛ هنگام کپی هم منتقل می‌شود.">
                @php $allowances = $pattern->seam_allowances ?? []; @endphp

                @if (empty($allowances))
                    <p class="text-sm text-stone-500">مقداری ثبت نشده است.</p>
                @else
                    <dl class="space-y-2 text-sm">
                        @foreach ($allowances as $edge => $value)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-stone-500">
                                    {{ ['default' => 'همه لبه‌ها', 'hem' => 'تودوزی'][$edge] ?? \App\Http\Controllers\WorkshopController::EDGE_LABELS[$edge] ?? $edge }}
                                </dt>
                                <dd class="font-medium">{{ \App\Support\Format::cm($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-advanced-section>
        </div>
    </div>
</x-app-layout>
