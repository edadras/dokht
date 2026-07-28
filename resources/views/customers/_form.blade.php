@use('App\Models\Customer')
@use('App\Support\Jalali')

{{-- فرم مشتری: فقط نام لازم است، بقیه اختیاری. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <x-field label="نام و نام خانوادگی" name="name" required class="sm:col-span-2">
        <x-input name="name" :value="$customer->name" required autofocus placeholder="مثلاً مریم احمدی" />
    </x-field>

    <x-field label="شماره تماس" name="phone" hint="می‌توانید با اعداد فارسی بنویسید.">
        <x-input name="phone" :value="$customer->phone" inputmode="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" />
    </x-field>

    <x-field label="جنسیت" name="gender">
        <x-select name="gender" :options="Customer::GENDERS" :selected="$customer->gender ?? 'female'" />
    </x-field>
</div>

<x-advanced-section title="اطلاعات بیشتر (اختیاری)" description="تاریخ تولد، ایمیل و یادداشت‌های شما دربارهٔ این مشتری."
    :open="$errors->hasAny(['email', 'birth_date', 'notes'])" class="mt-5">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="تاریخ تولد" name="birth_date" hint="به شکل ۱۳۷۰/۰۵/۱۲">
            <x-input name="birth_date" :value="$customer->birth_date ? Jalali::date($customer->birth_date) : null"
                placeholder="۱۳۷۰/۰۵/۱۲" />
        </x-field>

        <x-field label="ایمیل" name="email">
            <x-input type="email" name="email" :value="$customer->email" dir="ltr" class="text-left" />
        </x-field>

        <x-field label="یادداشت" name="notes" class="sm:col-span-2">
            <x-textarea name="notes" :value="$customer->notes" rows="3"
                placeholder="مثلاً: آستین را همیشه دو سانت بلندتر می‌خواهد." />
        </x-field>
    </div>
</x-advanced-section>
