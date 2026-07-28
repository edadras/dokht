{{-- فرم اجرای دوباره چیدمان؛ همه گزینه‌های حرفه‌ای با پیش‌فرض درست پر شده است --}}
@php
    $layout ??= null;
    $fabrics = $fabrics ?? collect();
@endphp

<form method="POST" action="{{ route('projects.cutting', $project) }}" class="space-y-5">
    @csrf

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="پارچه" name="fabric_id" hint="اگر خالی بگذارید، پارچه پروژه استفاده می‌شود.">
            <x-select name="fabric_id" placeholder="پارچه پروژه"
                :options="$fabrics->mapWithKeys(fn ($fabric) => [$fabric->id => $fabric->displayName().' — '.\App\Support\Format::cm($fabric->effectiveWidth())])"
                :selected="$layout?->fabric_id ?? $project->fabric_id" />
        </x-field>

        <x-field label="عرض پارچه" name="fabric_width_cm" suffix="سانتی‌متر"
            hint="عرض روی برچسب پارچه؛ معمولاً ۱۴۰ سانتی‌متر.">
            <x-input type="number" name="fabric_width_cm" step="0.5" min="30" max="320"
                :value="$layout?->fabric_width_cm ?? $project->fabric?->effectiveWidth() ?? 140" />
        </x-field>
    </div>

    <label class="flex items-start gap-3 rounded-xl border border-stone-200 bg-stone-50 p-4">
        <input type="hidden" name="folded" value="0">
        <input type="checkbox" name="folded" value="1" @checked($layout?->folded ?? true)
            class="mt-0.5 h-5 w-5 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
        <span>
            <span class="block text-sm font-semibold text-stone-800">پارچه را از عرض تا می‌کنم</span>
            <span class="block text-xs text-stone-500">
                حالت معمول خیاطی؛ قطعه‌های قرینه با یک بار برش دو تا در می‌آیند و قطعه‌های «روی تا» به لبه تا می‌چسبند.
            </span>
        </span>
    </label>

    <x-advanced-section title="تنظیمات حرفه‌ای برش"
        description="جهت خواب پارچه، تطبیق راه‌راه، فاصله قطعه‌ها و جای دوخت">
        <div class="space-y-4">
            @foreach ([
                ['respect_nap', 'رعایت جهت خواب پارچه (یک‌جهت بریدن)', 'برای مخمل، جیر و طرح‌های جهت‌دار لازم است؛ چرخش قطعه‌ها انجام نمی‌شود.', $layout?->respect_nap ?? ($project->fabric?->isDirectional() ?? false)],
                ['match_stripes', 'تطبیق راه‌راه و چهارخانه در درزها', 'قطعه‌ها روی تکرار طرح تنظیم می‌شوند؛ کمی پارچه بیشتر لازم می‌شود.', $layout?->match_stripes ?? ($project->fabric?->needsPatternMatching() ?? false)],
                ['include_allowance', 'جای دوخت را در چیدمان حساب کن', 'کادر هر قطعه به اندازه جای دوخت بزرگ‌تر گرفته می‌شود.', true],
                ['allow_rotation', 'اجازه چرخش ۹۰ درجه قطعه‌های بدون راستای ثابت', 'مصرف پارچه کم می‌شود، ولی برای پارچه جهت‌دار مناسب نیست.', true],
            ] as [$name, $title, $hint, $checked])
                <label class="flex items-start gap-3">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)
                        class="mt-0.5 h-5 w-5 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                    <span>
                        <span class="block text-sm font-medium text-stone-800">{{ $title }}</span>
                        <span class="block text-xs text-stone-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach

            <x-field label="فاصله بین قطعه‌ها" name="gap_cm" suffix="سانتی‌متر"
                hint="کمی فاصله برای دست‌بردن قیچی؛ پیش‌فرض ۱ سانتی‌متر.">
                <x-input type="number" name="gap_cm" step="0.5" min="0" max="15" value="1" />
            </x-field>
        </div>
    </x-advanced-section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-stone-500">
            یک بار دکمه را بزنید تا بگوییم چند متر پارچه لازم دارید، چقدر دورریز می‌شود و نقشه برش چطور است.
        </p>
        <x-button type="submit" icon="scissors">محاسبه چیدمان برش</x-button>
    </div>
</form>
