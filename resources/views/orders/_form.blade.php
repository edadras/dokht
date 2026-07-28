@use('App\Models\Order')
@use('App\Support\Jalali')

{{-- فرم سفارش: مشتری، عنوان و تاریخ تحویل کافی است. --}}
<x-card title="سفارش" icon="clipboard" subtitle="سه چیز لازم است: مشتری، عنوان کار و تاریخ تحویل.">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="عنوان کار" name="title" required class="sm:col-span-2"
            hint="مثلاً «مانتو اداری آستین بلند» یا «تنگ کردن شلوار».">
            <x-input name="title" :value="$order->title" required autofocus placeholder="مانتو اداری" />
        </x-field>

        <x-field label="مشتری" name="customer_id">
            <x-select name="customer_id" :options="$customers" :selected="$selectedCustomer" placeholder="— انتخاب از دفتر مشتری‌ها —" />
        </x-field>

        <x-field label="یا نام مشتری تازه" name="new_customer_name"
            hint="اگر مشتری در دفتر نیست، نامش را همین‌جا بنویسید تا ساخته شود.">
            <x-input name="new_customer_name" placeholder="مثلاً زهرا کریمی" />
        </x-field>

        <x-field label="تاریخ تحویل" name="due_date" hint="به شکل ۱۴۰۵/۰۵/۱۲ بنویسید.">
            <x-input name="due_date" :value="$order->due_date ? Jalali::date($order->due_date) : null"
                placeholder="{{ Jalali::date(now()->addDays(7)) }}" />
        </x-field>

        <x-field label="وضعیت" name="status">
            <x-select name="status" :selected="$order->status ?? 'new'"
                :options="collect(Order::STATUSES)->map(fn ($meta) => $meta['label'])->all()" />
        </x-field>

        <x-field label="مبلغ کل" name="price" suffix="تومان">
            <x-input name="price" :value="$order->price ? (int) $order->price : null" inputmode="decimal"
                class="pe-16" placeholder="۰" />
        </x-field>

        <x-field label="بیعانه" name="deposit" suffix="تومان" hint="اگر مشتری پیش‌پرداخت داده است.">
            <x-input name="deposit" :value="$order->deposit ? (int) $order->deposit : null" inputmode="decimal"
                class="pe-16" placeholder="۰" />
        </x-field>
    </div>
</x-card>

<x-advanced-section title="اتصال به پروژه و مسئول کار (اختیاری)"
    description="اگر برای این سفارش پروژه دوخت ساخته‌اید یا کار را به همکاری سپرده‌اید."
    :open="filled($selectedProject) || filled($order->assigned_to)" class="mt-5">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="پروژه دوخت" name="project_id">
            <x-select name="project_id" :options="$projects" :selected="$selectedProject" placeholder="— بدون پروژه —" />
        </x-field>

        <x-field label="مسئول کار" name="assigned_to">
            <x-select name="assigned_to" :options="$members" :selected="$order->assigned_to" placeholder="— تعیین نشده —" />
        </x-field>

        <x-field label="یادداشت سفارش" name="notes" class="sm:col-span-2">
            <x-textarea name="notes" :value="$order->notes" rows="3"
                placeholder="مثلاً: آستر مشکی، دکمه‌ها را خودش می‌آورد." />
        </x-field>
    </div>
</x-advanced-section>
