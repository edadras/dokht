<x-app-layout title="تنظیمات کارگاه">
    <x-page-header title="تنظیمات کارگاه" subtitle="نام، نشانی و پیش‌فرض‌های کارگاه شما.">
        <x-slot:actions>
            <x-button href="{{ route('workshop.members') }}" variant="secondary" icon="users">اعضای کارگاه</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('workshop.update') }}" enctype="multipart/form-data"
                class="space-y-6">
                @csrf
                @method('PATCH')

                <x-card title="شناسنامه کارگاه" icon="scissors"
                    subtitle="این اطلاعات بالای فاکتور و کارت فنی چاپ می‌شود.">
                    <div class="space-y-4">
                        <x-field label="نام کارگاه" name="name" required>
                            <x-input name="name" :value="$workshop->name" required autofocus />
                        </x-field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="شماره تماس" name="phone">
                                <x-input name="phone" :value="$workshop->phone" dir="ltr" class="text-left" />
                            </x-field>

                            <x-field label="شهر" name="city">
                                <x-input name="city" :value="$workshop->city" />
                            </x-field>
                        </div>

                        <x-field label="نشانی" name="address" hint="نشانی کوتاه؛ همان چیزی که روی فاکتور می‌نویسید.">
                            <x-input name="address" :value="$workshop->address" />
                        </x-field>

                        <x-field label="واحد پول" name="currency" hint="مثل «تومان». روی مبالغ سفارش نوشته می‌شود.">
                            <x-input name="currency" :value="$workshop->currency" />
                        </x-field>

                        <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                            <p class="text-sm font-medium text-stone-700">نشان (لوگو) کارگاه</p>

                            <div class="mt-3 flex flex-wrap items-center gap-4">
                                @if ($workshop->logoUrl())
                                    <img src="{{ $workshop->logoUrl() }}" alt="نشان {{ $workshop->name }}"
                                        class="h-20 w-20 rounded-2xl border border-stone-200 bg-white object-contain p-1">
                                @else
                                    <span
                                        class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-white text-stone-300">
                                        <x-icon name="scissors" class="h-8 w-8" />
                                    </span>
                                @endif

                                <div class="min-w-0 flex-1 space-y-2">
                                    <input type="file" name="logo" accept="image/*"
                                        class="block w-full cursor-pointer rounded-xl border border-stone-300 bg-white text-sm file:me-3 file:cursor-pointer file:rounded-e-xl file:border-0 file:bg-stone-100 file:px-4 file:py-2.5 file:text-sm file:text-stone-700">
                                    <p class="text-xs text-stone-500">تصویر PNG یا JPG، حجم کمتر از ۲ مگابایت.</p>

                                    @error('logo')
                                        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                                    @enderror

                                    @if ($workshop->logo_path)
                                        <label class="flex items-center gap-2 text-xs text-stone-600">
                                            <input type="checkbox" name="remove_logo" value="1"
                                                class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                                            نشان فعلی حذف شود
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-advanced-section title="پیش‌فرض‌های حرفه‌ای"
                    description="جای دوخت، عرض پارچه و شماره‌گذاری سفارش. اگر تازه‌کارید، همین مقادیر خوب است.">
                    <div class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="جای دوخت پیش‌فرض" name="default_seam_allowance" suffix="سانتی‌متر" required
                                hint="روی همه لبه‌ها، مگر لبه‌ای که پایین‌تر جدا تعیین شود.">
                                <x-input type="number" name="default_seam_allowance" step="0.1" min="0" max="10"
                                    :value="$workshop->default_seam_allowance" required class="pe-20" />
                            </x-field>

                            <x-field label="جای تودوزی (لب برگردان)" name="default_hem_allowance" suffix="سانتی‌متر"
                                required>
                                <x-input type="number" name="default_hem_allowance" step="0.1" min="0" max="20"
                                    :value="$workshop->default_hem_allowance" required class="pe-20" />
                            </x-field>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-stone-800">جای دوخت هر لبه</p>
                            <p class="mt-0.5 text-xs text-stone-500">
                                خالی بگذارید تا از مقدار پیش‌فرض بالا استفاده شود.
                            </p>

                            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                @foreach (\App\Http\Controllers\WorkshopController::EDGE_LABELS as $edge => $label)
                                    <x-field :label="$label" :name="'seam_allowances.'.$edge" suffix="سانتی‌متر">
                                        <input type="number" step="0.1" min="0" max="10"
                                            name="seam_allowances[{{ $edge }}]"
                                            value="{{ old('seam_allowances.'.$edge, $allowances[$edge] ?? '') }}"
                                            class="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 pe-20 text-sm shadow-sm transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                    </x-field>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="عرض پیش‌فرض پارچه" name="fabric_width" suffix="سانتی‌متر"
                                hint="برای تخمین مصرف و چیدمان برش استفاده می‌شود.">
                                <x-input type="number" name="fabric_width" step="1" min="50" max="320"
                                    :value="$workshop->defaultFabricWidth()" class="pe-20" />
                            </x-field>

                            <x-field label="پیش‌شماره سفارش" name="order_prefix"
                                hint="مثل «۱۴۰۴-»؛ ابتدای شماره سفارش‌های تازه می‌آید.">
                                <x-input name="order_prefix" :value="$workshop->orderPrefix()" />
                            </x-field>
                        </div>
                    </div>
                </x-advanced-section>

                <div class="flex justify-end">
                    <x-button type="submit" icon="check">ذخیره تنظیمات</x-button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <x-card title="کارگاه من" icon="home">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">مشتری‌ها</dt>
                        <dd class="font-bold">{{ \App\Support\Format::number($counts['customers']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">سفارش‌ها</dt>
                        <dd class="font-bold">{{ \App\Support\Format::number($counts['orders']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">الگوها</dt>
                        <dd class="font-bold">{{ \App\Support\Format::number($counts['patterns']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">پارچه‌ها</dt>
                        <dd class="font-bold">{{ \App\Support\Format::number($counts['fabrics']) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-stone-100 pt-4 text-xs text-stone-500">
                    <p>نشانی کارگاه در سامانه: <span dir="ltr">{{ $workshop->slug }}</span></p>
                </div>
            </x-card>

            <x-card title="اعضای کارگاه" icon="users" subtitle="دعوت همکار و تعیین نقش‌ها.">
                <p class="text-sm text-stone-600">
                    می‌توانید برای خیاط، الگوساز یا شاگرد خود دعوت‌نامه بسازید و نقش هرکس را جدا تعیین کنید.
                </p>
                <div class="mt-4">
                    <x-button href="{{ route('workshop.members') }}" variant="soft" icon="users" class="w-full">
                        مدیریت اعضا
                    </x-button>
                </div>
            </x-card>

            <x-card title="حذف کارگاه" icon="alert">
                <p class="text-sm text-stone-600">
                    حذف کارگاه از این صفحه انجام نمی‌شود و این کار عمدی است: با حذف کارگاه، همه مشتری‌ها، سفارش‌ها،
                    الگوها و اندازه‌های ثبت‌شده از بین می‌رود و راه بازگشتی ندارد.
                </p>
                <x-alert type="info" class="mt-4">
                    اگر واقعاً می‌خواهید کارگاه بسته شود، با پشتیبانی تماس بگیرید تا پس از گرفتن یک نسخه پشتیبان
                    برایتان انجام شود.
                </x-alert>
            </x-card>
        </div>
    </div>
</x-app-layout>
