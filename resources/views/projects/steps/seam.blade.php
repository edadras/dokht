@php
    use App\Support\Format;

    $severityTypes = ['warning' => 'warning', 'suggestion' => 'tip', 'info' => 'info'];

    // پیشنهادهای موتور قواعد که به الگو و دوخت مربوط‌اند
    $suggestions = collect($ruleFindings)
        ->filter(fn ($f) => in_array($f['scope'] ?? '', ['pattern', 'production', 'fit'], true))
        ->take(4);
@endphp

<x-app-layout title="مرحله ۵ — جای دوخت">
    <x-page-header title="چند سانتی‌متر جای دوخت بگذاریم؟" :subtitle="$project->name"
        :back="route('projects.show', $project)" />

    <x-step-nav :project="$project" current="5" />

    @if (! $project->pattern)
        <x-empty-state icon="scissors" title="اول الگو را بسازید"
            description="جای دوخت روی قطعه‌های الگو اضافه می‌شود، پس ابتدا باید الگو وجود داشته باشد.">
            <x-slot:action>
                <x-button :href="route('projects.step', [$project, 'pattern'])" icon="arrow-left">رفتن به مرحله‌ی الگو</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        @foreach ($suggestions as $finding)
            <x-alert :type="$severityTypes[$finding['severity'] ?? 'info'] ?? 'info'" :title="$finding['name'] ?? null"
                class="mb-4">
                {{ $finding['message'] ?? '' }}
            </x-alert>
        @endforeach

        <form method="POST" action="{{ route('projects.step.update', [$project, 'seam']) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-card title="جای دوخت هر لبه" icon="ruler"
                subtitle="مقدارهای پیش‌فرض کارگاه شما از قبل پر شده‌اند؛ اگر مطمئن نیستید همین‌ها را نگه دارید.">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($edges as $edge => $label)
                        <x-field :label="$label" :name="'allowances.'.$edge" suffix="س‌م"
                            :hint="'پیش‌فرض کارگاه: '.Format::number($defaults[$edge] ?? 1, 1)">
                            <x-input type="number" step="0.1" min="0" max="10" name="allowances[{{ $edge }}]"
                                :value="$allowances[$edge] ?? $defaults[$edge] ?? null" />
                        </x-field>
                    @endforeach
                </div>
            </x-card>

            <x-card>
                <x-button type="submit" size="lg" icon="arrow-left" class="w-full">ادامه</x-button>

                <p class="mt-4 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">
                    <b>سامانه چه کاری کرد؟</b>
                    این عددها روی خط دور هر قطعه اضافه می‌شوند؛ خط اصلی «خط دوخت» و خط بیرونی «خط برش» است.
                    در چاپ الگو و در چیدمان برش هر دو خط دیده می‌شود، پس دیگر لازم نیست موقع برش حساب کنید.
                </p>
            </x-card>

            <x-advanced-section title="راهنمای مقدارها" description="اگر شک دارید این توضیح کوتاه کمک می‌کند.">
                <ul class="space-y-2 text-sm leading-6 text-stone-600">
                    <li>• <b>پیش‌فرض</b> برای درزهای معمولی ۱ تا ۱٫۵ سانتی‌متر مناسب است.</li>
                    <li>• <b>پایین لباس</b> بین ۲ تا ۴ سانتی‌متر تا تو گذاشتن راحت باشد.</li>
                    <li>• <b>یقه</b> کم (حدود ۰٫۷) چون خط قوس‌دار با جای دوخت زیاد چین می‌خورد.</li>
                    <li>• برای پارچه‌ی ریش‌شو و لغزنده کمی بیشتر بگذارید تا درز باز نشود.</li>
                </ul>
            </x-advanced-section>
        </form>
    @endif
</x-app-layout>
