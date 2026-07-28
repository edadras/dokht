<x-app-layout title="اعضای کارگاه">
    <x-page-header title="اعضای کارگاه" subtitle="همکارانتان را دعوت کنید و دسترسی هرکس را تعیین کنید."
        :back="route('workshop.edit')" />

    @if (session('invite_link'))
        <div x-data="{ copied: false }"
            class="mb-6 rounded-2xl border border-brand-200 bg-brand-50 p-5">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                    <x-icon name="share" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-brand-900">لینک دعوت آماده است</p>
                    <p class="mt-1 text-sm text-brand-800">
                        این لینک را با پیامک یا واتساپ برای همکارتان بفرستید. او با باز کردن آن، نام و رمز خود را
                        می‌سازد و به کارگاه شما اضافه می‌شود.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <input type="text" readonly x-ref="link" value="{{ session('invite_link') }}" dir="ltr"
                            class="min-w-0 flex-1 rounded-xl border border-brand-200 bg-white px-3 py-2 text-left text-xs text-stone-700">
                        <x-button type="button" size="sm" variant="soft" icon="copy"
                            x-on:click="$refs.link.select(); document.execCommand('copy'); copied = true"
                            x-text="copied ? 'کپی شد' : 'کپی لینک'">کپی لینک</x-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="همکاران کارگاه" icon="users" padding="p-0">
                <div class="divide-y divide-stone-100">
                    @foreach ($members as $member)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                {{ $member->initials() }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-stone-900">
                                    {{ $member->name }}
                                    @if ($member->id === $workshop->owner_id)
                                        <x-badge color="brand" icon="star">مدیر اصلی</x-badge>
                                    @endif
                                    @if ($member->id === auth()->id())
                                        <x-badge color="slate">شما</x-badge>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-stone-500" dir="ltr">{{ $member->email }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($member->id === $workshop->owner_id || $member->id === auth()->id())
                                    <x-badge color="emerald">{{ $member->roleLabel() }}</x-badge>
                                @else
                                    <form method="POST" action="{{ route('workshop.members.update', $member) }}"
                                        class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role"
                                            class="w-40 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                            @foreach (\App\Models\User::ROLES as $value => $label)
                                                <option value="{{ $value }}" @selected($member->role === $value)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-button type="submit" size="sm" variant="secondary">ثبت نقش</x-button>
                                    </form>

                                    <x-confirm-delete :action="route('workshop.members.destroy', $member)"
                                        message="{{ $member->name }} از کارگاه شما حذف شود؟ داده‌های ثبت‌شده او باقی می‌ماند."
                                        label="حذف" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="دعوت‌نامه‌های باز" icon="share" padding="p-0">
                @if ($invites->isEmpty())
                    <div class="p-5">
                        <p class="text-sm text-stone-500">دعوت‌نامه بازی ندارید.</p>
                    </div>
                @else
                    <div class="divide-y divide-stone-100">
                        @foreach ($invites as $invite)
                            <div x-data="{ copied: false }" class="space-y-3 px-5 py-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-stone-900" dir="ltr">
                                            {{ $invite->email }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-stone-500">
                                            نقش پیشنهادی: {{ $invite->roleLabel() }} •
                                            ساخته‌شده در {{ \App\Support\Jalali::date($invite->created_at) }}
                                        </p>
                                    </div>

                                    <x-badge color="amber" icon="clock">در انتظار پذیرش</x-badge>

                                    <x-confirm-delete :action="route('workshop.invites.destroy', $invite)"
                                        message="این دعوت‌نامه لغو شود؟ لینک آن دیگر کار نمی‌کند." label="لغو" />
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="text" readonly x-ref="link" value="{{ $invite->url() }}" dir="ltr"
                                        class="min-w-0 flex-1 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2 text-left text-xs text-stone-600">
                                    <x-button type="button" size="sm" variant="ghost" icon="copy"
                                        x-on:click="$refs.link.select(); document.execCommand('copy'); copied = true"
                                        x-text="copied ? 'کپی شد' : 'کپی'">کپی</x-button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="دعوت همکار تازه" icon="plus">
                <form method="POST" action="{{ route('workshop.invites.store') }}" class="space-y-4">
                    @csrf

                    <x-field label="ایمیل همکار" name="email" required>
                        <x-input type="email" name="email" required dir="ltr" class="text-left"
                            placeholder="name@example.com" />
                    </x-field>

                    <x-field label="نقش او در کارگاه" name="role" required>
                        <x-select name="role" :options="$invitableRoles" selected="tailor" required />
                    </x-field>

                    <x-alert type="info">
                        ایمیل به‌طور خودکار فرستاده نمی‌شود؛ پس از ساختن دعوت‌نامه، لینک آن به شما نشان داده می‌شود تا
                        خودتان برای همکارتان بفرستید.
                    </x-alert>

                    <x-button type="submit" icon="share" class="w-full">ساختن لینک دعوت</x-button>
                </form>
            </x-card>

            <x-card title="هر نقش چه کاری می‌تواند بکند؟" icon="info">
                <dl class="space-y-3 text-sm">
                    @foreach (\App\Models\User::ROLES as $role => $label)
                        <div>
                            <dt class="font-semibold text-stone-800">{{ $label }}</dt>
                            <dd class="mt-0.5 text-xs leading-relaxed text-stone-600">
                                {{ \App\Http\Controllers\WorkshopController::ROLE_NOTES[$role] ?? '' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
